# Calendar of finds

Turns your *My Finds pocket query* from [geocaching.com](https://www.geocaching.com/pocket/)
to a SQLite database and gives you an overview on which calendar days (regardless of
year) you've found at least one geocache.

The project consists of two parts. (see [Usage](#usage) below)


## Requirements

- **Ruby** with the `nokogiri` and `sqlite3` gems to create the database
  ```bash
  gem install nokogiri sqlite3
  ```
- **PHP** with the `pdo_sqlite` extension enabled for viewing the calendar

## Usage

### 1. Convert the GPX file into a database

Extract the downloaded pocket query result before, as this is usually compressed.

```bash
ruby gpx2sqlite.rb path/to/finds.gpx gc.db
```

The script reads all `<wpt>` elements (caches) from the GPX file and creates one row
in the `caches` table for each found-like log entry (`Found it`, `Attended`,
`Webcam Photo Taken`), storing name, difficulty, terrain, size, cache type, log type,
and find date. CAUTION: An existing output file will be overwritten.

### 2. Start a web server

```bash
php -S localhost:8000
```

Then open `http://localhost:8000` in your browser.

If you are running an webserver already (Apache/nginx), copy the `calendar.php` and
the database file to a document root of the webserver.

`calendar.php` expects the file at `gc.db` in the same directory by default.

## How it works

- The calendar shows, for each day of the year (month + day, aggregated across all
  years), whether a find was ever logged on that calendar day (green check mark) or
  not (red cross).
- The "Cache-Typ" and "Cache-Größe" dropdown filters let you narrow the view down to
  specific cache types or sizes.
- The footer shows on how many of the 366 possible calendar days you already have a
  find.

## Notes

- The database is fully rebuilt every time `gpx2sqlite.rb` runs; there is no
  incremental update. (yet)
- `calendar.php` opens the database read-only (`SQLITE_OPEN_READONLY`).

## License

This project is licensed under the MIT License – see [LICENSE](LICENSE) for details.
