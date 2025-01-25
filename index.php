<?php
session_start();

// Sprawdzenie sesji dla numeru albumu
if (!isset($_SESSION['numerIndeksu'])) {
    echo 'Sesja została utracona lub jeszcze nie ustawiona.';
} else {
    echo 'Sesja działa poprawnie: ' . $_SESSION['numerIndeksu'];
}

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

    // Jeśli numer albumu został przesłany, zapisujemy go do zmiennej sesji
    if (isset($_POST['numerIndeksu']) && !empty($_POST['numerIndeksu'])) {
        $_SESSION['numerIndeksu'] = trim($_POST['numerIndeksu']);
    }

    // Pobranie numeru albumu z sesji
    $numerIndeksu = isset($_SESSION['numerIndeksu']) ? $_SESSION['numerIndeksu'] : '';

    // Pobranie innych danych z formularza
    $wykladowca = isset($_POST['wykladowca']) ? trim($_POST['wykladowca']) : '';
    $klasa = isset($_POST['klasa']) ? trim($_POST['klasa']) : '';
    $grupa = isset($_POST['grupa']) ? trim($_POST['grupa']) : '';
    $zajecia = isset($_POST['zajecia']) ? trim($_POST['zajecia']) : '';

    // Ustaw bieżącą datę lub wybraną przez użytkownika
    $selectedDate = isset($_POST['date']) && !empty($_POST['date']) ? $_POST['date'] : date('Y-m-d');
    $viewType = isset($_POST['view']) && $_POST['view'] === 'week' ? 'week' : 'day';

    // Oblicz zakres dat w zależności od widoku (dzień/tydzień)
    if ($viewType === 'week') {
        $startTime = date('Y-m-d', strtotime('monday this week', strtotime($selectedDate))) . 'T00:00:00';
        $endTime = date('Y-m-d', strtotime('sunday this week', strtotime($selectedDate))) . 'T23:59:59';
    } else {
        $startTime = $selectedDate . 'T00:00:00';
        $endTime = $selectedDate . 'T23:59:59';
    }

    // Budowanie zapytania SQL
    $query = "SELECT DISTINCT lekcja.tytul AS tytul, lekcja.start AS start, wykladowca.nazwisko AS wyk_nazwisko, 
              wykladowca.imie AS wyk_imie
              FROM lekcja
              INNER JOIN wykladowca ON lekcja.wykladowcaID = wykladowca.wykladowcaID
              INNER JOIN sala ON lekcja.salaID = sala.salaID
              INNER JOIN grupa ON lekcja.grupaID = grupa.grupaID
              INNER JOIN numeralbumugrupa ON lekcja.grupaID = numeralbumugrupa.grupaID
              WHERE lekcja.start BETWEEN ? AND ?";

    $params = [$startTime, $endTime];
    $types = "ss";

    // Dodanie warunku dla numeru albumu
    if (!empty($numerIndeksu)) {
        $query .= " AND numeralbumugrupa.numerAlbumuID = ?";
        $params[] = $numerIndeksu; // Bez symbolu %, bo to dokładne dopasowanie
        $types .= "s";
    }

    // Dodanie innych warunków
    if (!empty($grupa)) {
        $query .= " AND lekcja.grupaID = ?";
        $params[] = $grupa;
        $types .= "s";
    }

    if (!empty($zajecia)) {
        $query .= " AND lekcja.tytul LIKE ?";
        $params[] = "%$zajecia%";
        $types .= "s";
    }

    if (!empty($klasa)) {
        $query .= " AND sala.pokoj LIKE ?";
        $params[] = "%$klasa%";
        $types .= "s";
    }

    if (!empty($wykladowca)) {
        $query .= " AND (wykladowca.nazwisko LIKE ? OR wykladowca.imie LIKE ?)";
        $params[] = "%$wykladowca%";
        $params[] = "%$wykladowca%";
        $types .= "ss";
    }

    // Przygotowanie i wykonanie zapytania
    $lessonHTML = '';
    if ($query != "") {
        $stmt = $conn->prepare($query);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $lessonHTML .= '<section class="lesson-panel-container">';
            $lessonHTML .= '<div class="lesson-panels">';

            $currentDate = ''; // Zmienna do trzymania daty aktualnej lekcji

            while ($row = $result->fetch_assoc()) {
                $start_date = new DateTime($row['start']);
                $lessonDate = $start_date->format('Y-m-d');

                // Jeżeli data lekcji różni się od poprzedniej, rozpoczynamy nową sekcję
                if ($currentDate !== $lessonDate) {
                    if ($currentDate !== '') {
                        $lessonHTML .= '</div>'; // Zamykamy poprzednią datę
                    }
                    $lessonHTML .= '<div class="lesson-panel">';
                    $lessonHTML .= '<div class="lesson-header">';
                    $lessonHTML .= '<p class="lesson-date">' . $lessonDate . '</p>';
                    $lessonHTML .= '<hr class="divider-line"/>';
                    $lessonHTML .= '</div>';
                    $currentDate = $lessonDate;
                }

                // Dodawanie pojedynczej lekcji do panelu
                $lessonHTML .= '<div class="lesson">';
                $lessonHTML .= '<div class="lesson-title">';
                $lessonHTML .= '<p>' . htmlspecialchars($row['tytul'], ENT_QUOTES, 'UTF-8') . '</p>';
                $lessonHTML .= '</div>';
                $lessonHTML .= '<div class="lesson-time">';
                $lessonHTML .= '<p>' . $start_date->format('H:i') . '</p>';
                $lessonHTML .= '</div>';
                $lessonHTML .= '</div>';
            }

            $lessonHTML .= '</div>';
            $lessonHTML .= '</section>';
        } else {
            $lessonHTML = '<p>Brak zajęć w wybranym okresie.</p>';
        }

        $stmt->close();
    }

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
                <button class="confirm-btn" id="confirmDate">
                    <img alt="check" src="images/check.svg" />
                </button>
            </div>

            <div class="view-switch">
                <button class="view-btn" id="dayView">Dzień</button>
                <div class="divider"></div>
                <button class="view-btn" id="weekView">Tydzień</button>
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