<?php
declare(strict_types=1);

// Increase timeout for large AMFI file
set_time_limit(300);

function fetch_url(string $url): ?string {
    $ctx = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36\r\n",
            'timeout' => 60
        ]
    ]);
    return @file_get_contents($url, false, $ctx) ?: null;
}

// ---------------------------------------------------------
// 1. STOCKS (Yahoo Finance)
// ---------------------------------------------------------
function get_yahoo_price(string $symbol): ?float {
    $clean = preg_replace('/\.(NS|BO)$/i', '', trim($symbol));
    $ticker = $clean . '.NS'; 
    $url = "https://query1.finance.yahoo.com/v8/finance/chart/" . urlencode($ticker) . "?interval=1d&range=1d";
    $json = fetch_url($url);
    if ($json) {
        $data = json_decode($json, true);
        return $data['chart']['result'][0]['meta']['regularMarketPrice'] ?? null;
    }
    return null;
}

// ---------------------------------------------------------
// 2. MUTUAL FUNDS (Smart Token Matcher)
// ---------------------------------------------------------

// Clean and tokenize a name
function get_clean_tokens(string $name): array {
    $n = strtolower($name);
    // ASCII only
    $n = preg_replace('/[^\x20-\x7e]/', ' ', $n);
    
    // Expand Abbreviations (Crucial for "FoF" vs "Fund of Fund")
    $n = preg_replace('/\bfof\b/', 'fund of fund', $n);
    $n = preg_replace('/\betf\b/', 'exchange traded fund', $n);
    
    // Remove Noise Words (Don't match on these)
    $noise = ['scheme', 'plan', 'option', 'fund', 'mutual', 'isin', 'div', 'payout', 'reinvestment', '-', '(', ')'];
    $n = str_replace($noise, ' ', $n);
    
    // Split into unique words
    $words = explode(' ', $n);
    $tokens = [];
    foreach($words as $w) {
        $t = trim($w);
        if(strlen($t) > 1) $tokens[] = $t; // Ignore single chars like 'a'
    }
    return array_unique($tokens);
}

// Verify critical constraints
function check_constraints(string $dbName, string $amfiName): bool {
    $db = strtolower($dbName);
    $amfi = strtolower($amfiName);

    // Direct vs Regular
    if (strpos($db, 'direct') !== false && strpos($amfi, 'direct') === false && strpos($amfi, ' dir ') === false) return false;
    if (strpos($db, 'regular') !== false && (strpos($amfi, 'direct') !== false || strpos($amfi, ' dir ') !== false)) return false;

    // Growth vs IDCW
    if (strpos($db, 'growth') !== false && strpos($amfi, 'growth') === false && strpos($amfi, ' gr ') === false) return false;
    
    return true;
}

function get_amfi_data_grouped(): array {
    $url = "https://portal.amfiindia.com/spages/NAVAll.txt";
    $data = fetch_url($url);
    $grouped = [];
    if ($data) {
        $lines = explode("\n", $data);
        foreach ($lines as $line) {
            $parts = explode(";", $line);
            if (count($parts) >= 5) {
                $name = trim($parts[3]);
                $nav = (float)$parts[4];
                if ($nav > 0) {
                    // Group by first word for speed (e.g. "HDFC")
                    $firstWord = strtolower(strtok($name, " -"));
                    $grouped[$firstWord][] = ['name' => $name, 'nav' => $nav];
                }
            }
        }
    }
    return $grouped;
}

function find_best_match(string $dbName, array $groupedData): ?float {
    $firstWord = strtolower(strtok($dbName, " -"));
    
    // Fallback for names like "Aditya Birla..." where token might differ
    if (!isset($groupedData[$firstWord])) {
        // Try searching all groups if bucket misses? (Too slow) 
        // Instead, try a looser bucket check if strictly needed, but usually First Word is safe.
        return null;
    }

    $candidates = $groupedData[$firstWord];
    $dbTokens = get_clean_tokens($dbName);
    
    $bestMatch = null;
    $maxScore = 0;

    foreach ($candidates as $cand) {
        if (!check_constraints($dbName, $cand['name'])) continue;

        $amfiTokens = get_clean_tokens($cand['name']);
        
        // Strategy 1: Containment
        // Do ALL db tokens exist in AMFI tokens?
        $missing = array_diff($dbTokens, $amfiTokens);
        
        if (empty($missing)) {
            // Perfect containment! Prefer shortest name (closest match)
            $len = strlen($cand['name']);
            if ($bestMatch === null || $len < $bestMatch['len']) {
                $bestMatch = ['nav' => $cand['nav'], 'len' => $len];
            }
        }
    }

    if ($bestMatch) return $bestMatch['nav'];
    
    // Strategy 2: Fallback to similarity if no perfect subset found
    foreach ($candidates as $cand) {
        if (!check_constraints($dbName, $cand['name'])) continue;
        $p = 0;
        similar_text(strtolower($dbName), strtolower($cand['name']), $p);
        if ($p > 90) return $cand['nav']; // Very high confidence only
    }

    return null;
}

// ---------------------------------------------------------
// MAIN UPDATER
// ---------------------------------------------------------
function update_all_prices_db(PDO $pdo, int $userId): array {
    $stats = ['stocks' => 0, 'mf' => 0, 'errors' => []];
    
    // 1. Get Investments (Only those with positive holdings)
    // We sum up the transaction history to check current units > 0
    $sql = "
        SELECT i.id, i.asset_type, i.symbol, i.name
        FROM investments i
        JOIN investment_txs it ON it.investment_id = i.id
        WHERE i.user_id = ?
        GROUP BY i.id
        HAVING SUM(CASE WHEN it.side = 'BUY' THEN it.units ELSE -it.units END) > 0.001
    ";

    $st = $pdo->prepare($sql);
    $st->execute([$userId]);
    $rows = $st->fetchAll();
    
    $mf_data = [];
    $has_mf = false;
    foreach($rows as $r) if($r['asset_type'] === 'mutual_fund') $has_mf = true;
    
    if ($has_mf) {
        $mf_data = get_amfi_data_grouped();
        if (empty($mf_data)) $stats['errors'][] = "AMFI download failed.";
    }

    foreach ($rows as $r) {
        $price = null;
        
        // --- STOCK ---
        if ($r['asset_type'] === 'stock') {
            $price = get_yahoo_price($r['symbol']);
            if ($price) $stats['stocks']++;
            else $stats['errors'][] = "Stock: " . $r['symbol'];
        }
        
        // --- MUTUAL FUND ---
        elseif ($r['asset_type'] === 'mutual_fund') {
            if(!empty($mf_data)) {
                $price = find_best_match($r['name'], $mf_data);
                if ($price) $stats['mf']++;
                else $stats['errors'][] = "MF: " . substr($r['name'], 0, 15) . "...";
            }
        }

        if ($price !== null && $price > 0) {
            $pdo->prepare("UPDATE investments SET current_price=?, updated_at=datetime('now') WHERE id=?")
                ->execute([$price, $r['id']]);
        }
    }
    
    return $stats;
}
?>