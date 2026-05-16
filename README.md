# 🏈 Fantasy Football AI Assistant

Every fantasy season I make at least one roster move I regret by Tuesday night. This is my attempt to outsource the second-guessing to something that actually reads the injury reports.

It's a personal project: a PHP/MySQL web app that pulls **my real Yahoo Fantasy league data** (rosters, matchups, the waiver wire) and hands it to Claude AI for an honest opinion on who to start, who to bench, and who to grab before someone else does. No more "I had a feeling about that guy" — just my actual league, analyzed.

---

## What it does

You log in, link your Yahoo Fantasy account, and the app does the rest:

1. Pulls your leagues, roster, and team stats straight from the **Yahoo Fantasy Sports API**.
2. Grabs the top available players on your waiver wire.
3. Sends the whole picture to **Claude AI**, which researches current NFL injury news, snap counts, target share, and upcoming matchups.
4. Hands back specific, prioritized **add/drop and lineup recommendations** with the reasoning spelled out.

Then it gets out of your way and lets you make the call.

## Features

- **My Leagues** — see every Yahoo Fantasy league you're in and pick one to work on.
- **Dashboard** — your current roster, team stats (record, points), and the available-player pool in one place.
- **AI Analysis** — kicks off a deep analysis run with live status updates, then returns:
  - **Add recommendations** — who to pick up, with reasoning and a High/Medium/Low priority.
  - **Drop candidates** — who to cut, and why.
  - **Roster optimization** — bench inefficiencies and unused IR-spot opportunities.
  - **Player insights** — a closer look at any individual player.
- **Roster Optimizer** — suggests your best legal starting lineup for the week.
- **Direct Yahoo links** — every recommendation links straight to the Yahoo page to make the move.
- **Mobile-friendly** — built on Bootstrap 5, works on the couch or in line at the grocery store.

## Tech stack

- **Backend:** PHP 8.1+
- **Database:** MySQL 8.0+
- **Frontend:** Bootstrap 5.3.3, vanilla JavaScript (ES6) talking to the backend over AJAX
- **APIs:** Yahoo Fantasy Sports API (OAuth 2.0), Claude AI

## How the pieces fit

Each feature follows the same four-file pattern: `FeatureHTML.php` (UI), `Feature.js` (frontend + AJAX), `FeatureCode.php` (backend endpoints), and shared helpers in `common/`.

```
fantasy/
├── index.php              Entry point — routes you to login or dashboard
├── auth/                  Yahoo OAuth login/callback/logout
├── football/              The features: dashboard, analysis, leagues, roster optimizer
├── common/                Shared APIs and helpers
│   ├── YahooAPI.php          Yahoo Fantasy Sports integration
│   ├── ClaudeAPI.php         AI analysis (routes through aiCentral)
│   ├── common.php            Bootstrap + auth check
│   ├── aPRIV_DB.php          Database credentials  (you create this)
│   └── aPRIV_API.php         Yahoo API credentials (you create this)
└── schema.sql                  Database schema (one script, run once)
```

## Setup

1. **Database** — load the schema. The script creates the `fantasy` database for you:
   ```
   mysql -u youruser -p < schema.sql
   ```
2. **Credentials** — copy the example config files and fill in your own values:
   ```
   cp common/aPRIV_DB.php.example  common/aPRIV_DB.php
   cp common/aPRIV_API.php.example common/aPRIV_API.php
   ```
   - `aPRIV_DB.php` — your MySQL host, user, and password.
   - `aPRIV_API.php` — your Yahoo API client ID/secret (register an app at
     [developer.yahoo.com/apps](https://developer.yahoo.com/apps/)) and your callback URL.
3. **Serve it** — point a PHP-capable web server at the `fantasy/` directory and open `index.php`.

## Dependencies on companion projects

This started as part of a larger personal suite, so two pieces live outside this repo:

- **AI ([aiCentral](https://github.com/rfrench2k/aiCentral))** — the AI recommendations route through a shared
  AI hub via `ai_makeRequest()` instead of calling the Claude API directly. aiCentral is public; clone it
  alongside this project to run the analysis features.
- **Auth** — login goes through a shared authentication service. It's a thin dependency: swap
  `common/common.php`'s auth check for your own login (or the bundled Yahoo OAuth flow in `auth/`) and
  you're standalone.

## A note on this being a personal project

This was built for one user — me — to win a fantasy league or two. It's shared because the
Yahoo-API-plus-AI pattern might be useful to someone else, not because it's a polished product.
Expect rough edges, and may your waiver claims go through.
