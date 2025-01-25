<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "test";

try {
    // Połączenie z bazą danych
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    // Przygotowanie danych z formularza
    $wykladowca = isset($_POST['wykladowca']) ? trim($_POST['wykladowca']) : '';
    $klasa = isset($_POST['klasa']) ? trim($_POST['klasa']) : '';
    $grupa = isset($_POST['grupa']) ? trim($_POST['grupa']) : '';
    $numerIndeksu = isset($_POST['numerIndeksu']) ? trim($_POST['numerIndeksu']) : '';
    $zajecia = isset($_POST['zajecia']) ? trim($_POST['zajecia']) : '';

    // Budowanie zapytania SQL
    $query = "SELECT DISTINCT lekcja.tytul AS tytul, lekcja.start AS start, wykladowca.nazwisko AS wyk_nazwisko, 
                wykladowca.imie AS wyk_imie
FROM lekcja
INNER JOIN wykladowca ON lekcja.wykladowcaID = wykladowca.wykladowcaID
INNER JOIN sala ON lekcja.salaID = sala.salaID
INNER JOIN grupa ON lekcja.grupaID = grupa.grupaID
INNER JOIN numeralbumugrupa ON lekcja.grupaID = numeralbumugrupa.grupaID
";

    $params = [];
    $types = "";

    // Dodanie warunku dla grupy
    if (!empty($grupa)) {
        $query .= " WHERE lekcja.grupaID = ?";
        $params[] = $grupa;
        $types .= "s";
    }

    // Dodanie warunku dla zajęć (tytułu)
    if (!empty($zajecia)) {
        if (empty($grupa) && empty($wykladowca) && empty($klasa)) {
            $query .= " WHERE";
        } else {
            $query .= " AND";
        }
        $query .= " lekcja.tytul LIKE ?";
        $params[] = "%$zajecia%";
        $types .= "s";
    }

    // Dodanie warunku dla klasy (sala)
    if (!empty($klasa)) {
        if (empty($grupa) && empty($zajecia) && empty($wykladowca)) {
            $query .= " WHERE";
        } else {
            $query .= " AND";
        }
        $query .= " sala.pokoj LIKE ?";
        $params[] = "%$klasa%";  // Szukamy w sali (pokoju)
        $types .= "s";
    }

    // Dodanie warunku dla nauczyciela (wykładowca)
    if (!empty($wykladowca)) {
        if (empty($grupa) && empty($zajecia) && empty($klasa)) {
            $query .= " WHERE";
        } else {
            $query .= " AND";
        }
        $query .= " (wykladowca.nazwisko LIKE ? OR wykladowca.imie LIKE ?)";
        $params[] = "%$wykladowca%";  // Szukamy nauczyciela po nazwisku
        $params[] = "%$wykladowca%";  // Szukamy nauczyciela po imieniu
        $types .= "ss"; // Typy dla dwóch parametrów
    }

    if (!empty($numerIndeksu)) {
        if (empty($grupa) && empty($zajecia) && empty($klasa) && empty($wykladowca) && empty($numerIndeksu)) {
            $query .= " WHERE";
        } else {
            $query .= " AND";
        }

        // Dodajemy warunek dla numeru indeksu
        $query .= " numeralbumugrupa.numerAlbumuID LIKE ?";
        $params[] = $numerIndeksu;  // Dodajemy numer indeksu z symbolem %
        $types .= "s";  // Określamy, że parametr jest typu string
    }
//150
    // Dodanie warunku daty
    $query .= " AND lekcja.start BETWEEN '2025-01-17T00:00:00' AND '2025-01-18T23:59:59' LIMIT 20";

    $lessonHTML = '';

    if ($query != "") {
        $stmt = $conn->prepare($query);
        if ($types) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $lessonHTML .= '<section class="lesson-panel-container">';
            $lessonHTML .= '<div class="lesson-panels">';

            $currentDate = ''; // Zmienna do trzymania daty aktualnej lekcji

            while ($row = $result->fetch_assoc()) {
                $start_date = new DateTime($row['start']); // Pobieramy datę rozpoczęcia
                $end_date = isset($row['koniec']) ? new DateTime($row['koniec']) : $start_date; // Zabezpieczenie na wypadek braku daty zakończenia
                $lessonDate = $start_date->format('Y-m-d'); // Formatowanie daty

                // Jeżeli data lekcji różni się od poprzedniej, rozpoczynamy nową sekcję
                if ($currentDate !== $lessonDate) {
                    if ($currentDate !== '') {
                        $lessonHTML .= '</div>'; // Zamykamy poprzednią datę
                    }
                    $lessonHTML .= '<div class="lesson-panel">'; // Nowy panel lekcji
                    $lessonHTML .= '<div class="lesson-header">';
                    $lessonHTML .= '<p class="lesson-date">' . $lessonDate . '</p>';
                    $lessonHTML .= '<hr class="divider-line"/>';
                    $lessonHTML .= '</div>';
                    $currentDate = $lessonDate; // Ustawiamy nową datę jako bieżącą
                }

                // Dodawanie pojedynczej lekcji do panelu
                $lessonHTML .= '<div class="lesson">';
                $lessonHTML .= '<div class="lesson-title">';
                $lessonHTML .= '<p>' . htmlspecialchars($row['tytul'], ENT_QUOTES, 'UTF-8') . '</p>';
                $lessonHTML .= '</div>';
                $lessonHTML .= '<div class="lesson-time">';
                $lessonHTML .= '<p>' . $start_date->format('H:i') . ' - ' . $end_date->format('H:i') . '</p>';
                $lessonHTML .= '</div>';
                $lessonHTML .= '</div>';
            }

            $lessonHTML .= '</div>';
            $lessonHTML .= '</section>';
        } else {
            $lessonHTML = '<p>Brak zajęć w wybranym okresie.</p>';
        }

        $stmt->close();
    }// Przygotowanie i wykonanie zapytania


} catch (Exception $e) {
    $lessonHTML = "<p>Błąd: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</p>";
}

?>


<!DOCTYPE html>
<html lang="pl">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta charset="UTF-8">
    <title>Plan ZUT</title>
    <link rel="stylesheet" href="style.css">
    <script type="text/javascript" src="darkmode.js" defer></script>
    <script type="text/javascript" src="fontSize.js" defer></script>
    <script type="text/javascript" src="filers.js" defer></script>
</head>
<body>
<main>
    <aside class="sidebar">
        <img class="logo" alt="logo" src="images/logo.png"/>
        <form method="post" action="">
            <label>
                <input class="formInput" name="wykladowca" placeholder="wykładowca">
                <input class="formInput" name="klasa" placeholder="klasa">
                <input class="formInput" name="grupa" placeholder="numer grupy">
                <input class="formInput" name="numerIndeksu" placeholder="numer indeksu">
                <input class="formInput" name="zajecia" placeholder="zajęcia">
                <div>
                    <button type="submit" class="formInput">
                        <img alt="check" src="images/check.svg"/>
                        <p>ACCEPT</p>
                    </button>
                </div>
            </label>
        </form>
        <div class="option">
            <div>
                <img alt="font size" src="images/Aa.svg"/>
                <div class="textSize">
                    <p data-size="small">SMALL</p>
                    <p data-size="medium">MEDIUM</p>
                    <p data-size="big">BIG</p>
                </div>
            </div>
            <div>
                <button class="darkMode-btn" id="darkModeToggle">
                    <img alt="moon" src="images/moon.svg"/>
                    <p>DARK MODE</p>
                </button>
            </div>
        </div>
    </aside>
    <section class="content">
        <div class="navigation">
            <button class="nav-button left">&#8592;</button>
            <div class="divider"></div>
            <button class="nav-button right">&#8594;</button>
        </div>
        <div class="switch-container">
            <div class="date-switch">
                <label for="dateInput">Wybierz datę:</label>
                <input id="dateInput" type="date" name="date" />
                <button class="confirm-btn">
                    <img alt="check" src="images/check.svg" />
                </button>
            </div>

            <div class="view-switch">
                <button class="view-btn">Dzień</button>
                <div class="divider"></div>
                <button class="view-btn">Tydzień</button>
            </div>
        </div>
        <button class="add-btn">
            <img alt="plus" src="images/plus.svg" />
        </button>
        <div class="slide">
            <?php echo $lessonHTML; ?>
        </div>
    </section>
</main>
</body>
</html>
