# Forum Migration Tool

A simple and modular migration tool that helps developers migrate forum data between **XenForo**, **vBulletin**, **phpBB**, and **MyBB** platforms.

This tool is designed to simplify the process of importing categories, forums, threads, posts, and attachments between different community systems.  
Currently supports **XenForo → vBulletin** migration (stable), and includes partial structure for **phpBB** and **MyBB** (coming soon).

---

## 🚀 Features

- 🧱 Category and forum structure migration  
- 🧩 Clean modular PHP code (no framework required)  
- 💬 JSON-based AJAX responses for integration with admin panels  
- 🧠 Easy to extend for new forum types  
- 🛠 Debug-safe mode (logs unexpected outputs)

---

## 🗂 Folder Structure

```
/modules
│
├── xenforo/
│   ├── db_connection_ajax.php   # Handles XenForo DB connection via AJAX
│   ├── class_xenforo_importer.php
│   └── ...
│
├── vb/
│   ├── class_vb_importer.php
│   └── ...
│
├── phpbb/                      # Coming soon
├── mybb/                       # Coming soon
│
└── common/
    ├── functions.php           # Shared functions for all importers
    └── config.php              # Basic configuration
```

---

## ⚙️ Installation

1. **Upload** the files to your server inside your project directory (e.g., `/modules/migration/`).
2. Make sure your PHP version is **7.4 or higher**.
3. Set correct file permissions for PHP to read the forum root directories.

---

## 🔌 Configuration

Inside `db_connection_ajax.php`, the script tries to automatically detect your forum root.  
If not found, you can manually set it like this:

```php
$vb_root = '/home/username/public_html/vb6/';
```

You can also adjust other database settings in `config.php`.

---

## 🧪 Usage

1. Open the migration page (e.g., `/admin/migration/index.php`).
2. Click **"Test Connection"** to verify that XenForo and vBulletin are both accessible.
3. Click **"Start Migration"** to begin transferring data.

The system will log each step:
```
[2025-09-24 21:22:57] Fetched 0 nodes from XenForo at offset 20
```

Logs are stored in `/logs/migration.log`.

---

## 🧰 Error Handling

If you see an error like:

```
SyntaxError: JSON.parse: unexpected character at line 1
```

It means the PHP file returned invalid JSON (likely a warning or HTML output).  
To debug this:

1. Enable logging by adding this at the top of your PHP file:
   ```php
   error_reporting(E_ALL);
   ini_set('display_errors', 0);
   header('Content-Type: application/json; charset=utf-8');
   ob_start();
   ```
2. Add this at the bottom:
   ```php
   $output = ob_get_clean();
   if (trim($output) !== '') {
       file_put_contents(__DIR__ . '/ajax_error.log', "[".date('Y-m-d H:i:s')."] Unexpected output:\n".$output."\n", FILE_APPEND);
   }
   ```

Now all unexpected output will be saved into `ajax_error.log`.

---

## 🧩 Extending the Tool

If you want to add support for a new forum type (for example, SMF or Discourse):

1. Create a new folder under `/modules/your_forum_name/`
2. Add:
   - `class_yourforum_importer.php`
   - `db_connection_ajax.php`
3. Make sure your importer class follows this structure:
   ```php
   class YourForumImporter {
       public function connect() { ... }
       public function fetch_categories() { ... }
       public function fetch_forums() { ... }
       public function import_to_target() { ... }
   }
   ```
4. Update `/index.php` to show the new forum in the list with a color and “Coming soon” label.

---

## 🎨 UI Example (Index Page)

| Forum Type | Status         | Color  |
|-------------|----------------|--------|
| XenForo     | ✅ Supported    | Blue   |
| vBulletin   | ✅ Supported    | Green  |
| phpBB       | 🕓 Coming soon  | Orange |
| MyBB        | 🕓 Coming soon  | Purple |

---

## 🧑‍💻 Contributing

Contributions are welcome!  
If you'd like to improve this tool, feel free to:

- Submit a pull request
- Open an issue
- Suggest new features (e.g., SMF, Discourse, or IPB support)

Please follow clean code practices:
- English comments only  
- Consistent naming (snake_case for functions, PascalCase for classes)  
- Use JSON for AJAX communication  

---

## 📜 License

This project is licensed under the ** GPL-2.0 license ** — feel free to use and modify it for your own needs.

---

## ✉️ Contact

🌐 [web-start.net](https://www.web-start.net/forum/) | [vbulletin.com](https://forum.vbulletin.com/)
