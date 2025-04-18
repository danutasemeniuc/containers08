# Lucrare de laborator nr. 8: Integrare Continuă cu GitHub Actions

## Scopul lucrării

Această lucrare are ca scop familiarizarea cu procesul de integrare continuă folosind GitHub Actions într-un mediu bazat pe containere Docker. Prin parcurgerea acestei lucrări, veți crea o aplicație Web simplă în PHP, veți scrie teste pentru aceasta și veți configura rularea automată a testelor la fiecare push sau pull request în branch-ul principal.

---

## Sarcina lucrării
- Crearea unei aplicații Web folosind PHP.
- Scrierea testelor unitare pentru aplicație.
- Configurarea unui workflow GitHub Actions pentru integrare continuă.
- Utilizarea containerelor Docker pentru rularea aplicației și a testelor.

---

## Pași de executare

### 1. Pregătirea mediului
- Asigură-te că ai Docker instalat pe calculatorul tău.
- Creează un repository pe GitHub numit `containers08` (deja creat).
- Clonează repository-ul pe calculatorul tău:
  ```bash
  git clone https://github.com/utilizatorul-tau/containers08.git
  cd containers08
  ```

### 2. Structura proiectului
Creează următoarea structură de directoare:
```bash
containers08/
├── site/
│   ├── modules/
│   ├── templates/
│   ├── styles/
│   ├── sql/
│   ├── index.php
│   └── config.php
├── tests/
│   ├── testframework.php
│   └── tests.php
├── Dockerfile
├── .github/
│   └── workflows/
│       └── main.yml
└── README.md
```

### 3. Crearea aplicației Web PHP

#### Fișier: `site/modules/database.php`
Acesta definește clasa `Database`, care interacționează cu SQLite:
```php
<?php
class Database {
    private $pdo;

    public function __construct($path) {
        $this->pdo = new PDO("sqlite:" . $path);
    }

    public function Execute($sql) {
        return $this->pdo->exec($sql);
    }

    public function Fetch($sql) {
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function Create($title, $content) {
        $stmt = $this->pdo->prepare("INSERT INTO page (title, content) VALUES (?, ?)");
        return $stmt->execute([$title, $content]);
    }

    public function Read($id) {
        $stmt = $this->pdo->prepare("SELECT * FROM page WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function Update($id, $title, $content) {
        $stmt = $this->pdo->prepare("UPDATE page SET title = ?, content = ? WHERE id = ?");
        return $stmt->execute([$title, $content, $id]);
    }

    public function Delete($id) {
        $stmt = $this->pdo->prepare("DELETE FROM page WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function Count() {
        return $this->pdo->query("SELECT COUNT(*) FROM page")->fetchColumn();
    }
}
```

#### Fișier: `site/modules/page.php`
Clasa `Page` încarcă și procesează șabloane HTML:
```php
<?php
class Page {
    private $template;

    public function __construct($templatePath) {
        $this->template = file_get_contents($templatePath);
    }

    public function Render($data) {
        $output = $this->template;
        foreach ($data as $key => $value) {
            $output = str_replace("{{" . $key . "}}", $value, $output);
        }
        echo $output;
    }
}
```

#### Fișier: `site/templates/index.tpl`
```html
<!DOCTYPE html>
<html>
<head>
  <title>{{title}}</title>
  <link rel="stylesheet" href="styles/style.css">
</head>
<body>
  <h1>{{title}}</h1>
  <div>{{content}}</div>
</body>
</html>
```

#### Fișier: `site/styles/style.css`
```css
body {
  font-family: sans-serif;
  padding: 2rem;
  background: #f0f0f0;
}
h1 {
  color: darkblue;
}
```

#### Fișier: `site/index.php`
```php
<?php
require_once "config.php";
require_once "modules/database.php";
require_once "modules/page.php";

$db = new Database($config["db"]["path"]);
$page = new Page("templates/index.tpl");

$data = $db->Read(1);
$page->Render($data);
```

#### Fișier: `site/config.php`
```php
<?php
$config = ["db" => ["path" => "/var/www/db/db.sqlite"]];
```

### 4. Crearea bazei de date

#### Fișier: `site/sql/schema.sql`
```sql
CREATE TABLE page (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title TEXT,
    content TEXT
);

INSERT INTO page (title, content) VALUES ('Page 1', 'Content 1');
INSERT INTO page (title, content) VALUES ('Page 2', 'Content 2');
INSERT INTO page (title, content) VALUES ('Page 3', 'Content 3');
```

### 5. Crearea testelor

#### Fișier: `tests/testframework.php`
```php
<?php
class TestFramework {
    private $tests = [];

    public function add($name, $callback) {
        $this->tests[] = ["name" => $name, "callback" => $callback];
    }

    public function run() {
        foreach ($this->tests as $test) {
            echo "Test: " . $test["name"] . "... ";
            try {
                $test["callback"]();
                echo "PASSED\n";
            } catch (Exception $e) {
                echo "FAILED: " . $e->getMessage() . "\n";
            }
        }
    }
}
```

#### Fișier: `tests/tests.php`
```php
<?php
require_once "../site/config.php";
require_once "../site/modules/database.php";
require_once "../site/modules/page.php";
require_once "testframework.php";

$tests = new TestFramework();

$tests->add("Count pages", function () use ($config) {
    $db = new Database($config["db"]["path"]);
    if ($db->Count() < 1) throw new Exception("No pages found");
});

$tests->add("Render page", function () {
    $page = new Page("../site/templates/index.tpl");
    ob_start();
    $page->Render(["title" => "Test", "content" => "Hello"]);
    $output = ob_get_clean();
    if (strpos($output, "Hello") === false) throw new Exception("Render failed");
});

$tests->run();
```

### 6. Crearea Dockerfile

#### Fișier: `Dockerfile`
```Dockerfile
FROM php:7.4-fpm as base

RUN apt-get update && \
    apt-get install -y sqlite3 libsqlite3-dev && \
    docker-php-ext-install pdo_sqlite

VOLUME ["/var/www/db"]

COPY site/sql/schema.sql /var/www/db/schema.sql

RUN echo "prepare database" && \
    cat /var/www/db/schema.sql | sqlite3 /var/www/db/db.sqlite && \
    chmod 777 /var/www/db/db.sqlite && \
    rm -rf /var/www/db/schema.sql && \
    echo "database is ready"

COPY site /var/www/html
```

### 7. Configurarea GitHub Actions

#### Fișier: `.github/workflows/main.yml`
```yaml
name: CI

on:
  push:
    branches:
      - main
  pull_request:
    branches:
      - main

jobs:
  build:
    runs-on: ubuntu-latest
    steps:
      - name: Checkout
        uses: actions/checkout@v4

      - name: Build the Docker image
        run: docker build -t containers08 .

      - name: Create container
        run: docker create --name container --volume database:/var/www/db containers08

      - name: Copy tests to the container
        run: docker cp ./tests container:/var/www/html

      - name: Up the container
        run: docker start container

      - name: Run tests
        run: docker exec container php /var/www/html/tests/tests.php

      - name: Stop the container
        run: docker stop container

      - name: Remove the container
        run: docker rm container

      - name: Remove Docker image
        run: docker rmi containers08 || true
```

### 8. Testare
Fă push pe branch-ul `main`:
```bash
git add .
git commit -m "Lab 8 CI implemented"
git push origin main
```
Apoi mergi la fila **Actions** din GitHub pentru a vedea rularea testelor.

---

## Răspunsuri la întrebări

**1. Ce este integrarea continuă?**  
Integrarea continuă (Continuous Integration) este o practică DevOps care presupune integrarea codului sursă într-un repository partajat de câteva ori pe zi. Fiecare integrare este verificată automat printr-un build și execuția testelor.

**2. Pentru ce sunt necesare testele unitare? Cât de des trebuie să fie executate?**  
Testele unitare verifică funcționarea corectă a componentelor individuale ale aplicației. Ele trebuie să fie executate la fiecare modificare a codului pentru a preveni regresiile.

**3. Ce modificări trebuie făcute în `.github/workflows/main.yml` pentru a rula testele la fiecare Pull Request?**  
Se adaugă secțiunea `pull_request` în blocul `on:`:
```yaml
on:
  push:
    branches:
      - main
  pull_request:
    branches:
      - main
```
Astfel, workflow-ul se va declanșa atât la `push`, cât și la `pull_request` către `main`.

**4. Ce trebuie adăugat pentru a șterge imaginile create după testare?**  
Se adaugă la finalul jobului următorul pas:
```yaml
- name: Remove Docker image
  run: docker rmi containers08 || true
```
`|| true` asigură că, dacă imaginea nu există, comanda nu va opri procesul.

---

## Concluzii

Această lucrare de laborator a demonstrat importanța integrării continue în dezvoltarea modernă de software. Prin combinarea PHP, SQLite, Docker și GitHub Actions, am obținut o soluție complet automatizată de testare. Acest flux asigură o calitate constantă a codului și o livrare rapidă a funcționalității.

