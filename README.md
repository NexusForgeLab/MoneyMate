# 💰 MoneyMate
**Your Personal Finance & Investment Portfolio Tracker**

MoneyMate is a self-hosted, privacy-focused web application to track your income, expenses, and investments. It runs locally using PHP and SQLite, ensuring your financial data never leaves your machine.

![MoneyMate Dashboard](assets/icon-512.png)

## 🚀 Key Features

### 📊 Dashboard & Analytics
* **Net Worth Calculator:** Live view of your total wealth (Cash + Stocks + Mutual Funds).
* **Trend Analysis:** Interactive charts showing income vs. expenses over the last 12 months.
* **Category Breakdown:** See exactly where your money goes.

### 📈 Investment Portfolio (New!)
* **Groww Integration:** Seamlessly import your **Stock** and **Mutual Fund** "Order History" CSVs from Groww.
* **Auto-Calculation:** Automatically calculates average buy price, current holdings, and unrealized Profit & Loss.
* **Transaction History:** Keeps a full record of every Buy, Sell, SIP, and Redemption.
* **Duplicate Protection:** Smart import logic prevents duplicate entries if you upload the same file twice.

### 🔄 Automation
* **Recurring Transactions:** Set up your Salary or Monthly Subscriptions once, and they auto-add to your ledger every month.

### 📱 Mobile Experience (PWA)
* **Installable App:** Add to your phone's home screen (Android & iOS).
* **App Icon:** Dedicated launcher icon.
* **Fullscreen Mode:** Feels like a native app.

### 🔒 Security & Data
* **Single User:** Designed for personal use with a secure login.
* **Local DB:** Uses SQLite. No external database server required.
* **Backup:** One-click download of your entire database (`.db`) from Settings.

---

## 🛠️ Installation (Docker)

The easiest way to run MoneyMate is using Docker.

### Prerequisites
* [Docker Desktop](https://www.docker.com/products/docker-desktop) installed.

### Step-by-Step Setup

1.  **Download the Code**
    Place all the project files in a folder named `MoneyMate`.

2.  **Start the Container**
    Open your terminal/command prompt in the folder and run:
    ```bash
    docker compose up -d
    ```

3.  **Run the Installer**
    Open your browser and visit:
    http://localhost:8000/install.php

    * This will create the database and the default admin user.
    * **Default Login:** `admin`
    * **Default Password:** `admin123`

4.  **Login**
    Go to `http://localhost:8000/login.php` and sign in.

---

## 📖 How to Use

### 1. Tracking Investments (Groww Import)
MoneyMate is optimized for **Groww** users.
1.  Log in to Groww Web.
2.  Click your profile → **Reports**.
3.  Scroll down to **Transactions** (Important: Do not use "Holdings").
4.  Download **Stocks - Order history** and **Mutual Funds - Order history**.
5.  Open the downloaded files in Excel/Sheets and **Save As CSV (Comma delimited)**.
6.  In MoneyMate, go to **Investments** and upload the CSVs.
    * *Note: Sold stocks or redeemed funds are automatically hidden from the active portfolio view but saved in history.*

### 2. Setting up Recurring Items
1.  Go to the **Recurring** tab.
2.  Add your **Salary** (Income) and select the day (e.g., 1st of month).
3.  Add **Subscriptions** (Expense) like Netflix, Gym, etc.
4.  MoneyMate will automatically create these transactions when you visit the dashboard on/after that date.

### 3. Installing on Mobile
* **Android (Chrome):** Open the site → Tap "Add to Home screen" prompt or 3-dot menu → Install App.
* **iOS (Safari):** Tap Share button → "Add to Home Screen".

### 4. Backups
* Go to **Settings**.
* Click **Download Database**. Save this `.db` file securely.
* To restore: Replace the `data/finance.db` file in your project folder with your backup.

---

## 📂 Project Structure


```

MoneyMate/
├── app/
│   ├── db.php          # Database connection
│   ├── auth.php        # Login session logic
│   ├── finance.php     # Financial calculations & logic
│   └── layout.php      # HTML Header/Footer & PWA links
├── assets/             # CSS & Icons
├── data/               # SQLite Database (created after install)
├── api/                # Internal JSON APIs for charts
├── docker-compose.yml  # Container config
├── index.php           # Dashboard
├── investments.php     # Portfolio Manager
├── recurring.php       # Recurring Manager
└── sw.js               # Service Worker for PWA

```

## ⚠️ "Danger Zone"
If your investment data gets messy (e.g., wrong CSV imports):
1.  Go to the **Investments** page.
2.  Scroll to the bottom.
3.  Click **Reset All Data** to wipe only the investment tables and start fresh.

---
*Created for personal financial freedom.*