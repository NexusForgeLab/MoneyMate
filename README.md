# FinTrack (PHP + SQLite) — Docker Ready

FinTrack is a lightweight personal finance tracker:
- Add **income** (salary / other)
- Add **expenses** with category + reason
- Charts: **line** (trend), **pie** (income vs expense, expense categories)
- Track **stocks & mutual funds** manually
- Profit tracking using **current price** + holdings

## Run (Docker)
```bash
docker compose up -d --build
```

Open:
- http://YOUR_SERVER_IP:8093/install.php  (one time)
- then http://YOUR_SERVER_IP:8093

Default users:
- admin / admin123
- user / user123

After install, delete install.php:
```bash
rm install.php
```

## If you see "unable to open database file"
```bash
mkdir -p data
sudo chown -R 33:33 data
sudo chmod -R 775 data
docker compose restart
```
