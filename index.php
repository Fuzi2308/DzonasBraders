<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "test";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = "SELECT DISTINCT przedmiotID, tytul, start, koniec, grupaID FROM lekcja WHERE start BETWEEN '2025-01-12' AND '2025-01-18' AND grupaID = 7 ORDER BY start";

$result = $conn->query($sql);

$lessonHTML = '';

if ($result->num_rows > 0) {
    $lessonHTML .= '<section class="lesson-panel-container">';
    $lessonHTML .= '<div class="lesson-panels">';

    $currentDate = '';

    while ($row = $result->fetch_assoc()) {
        $start_date = new DateTime($row['start']);
        $end_date = new DateTime($row['koniec']);
        $lessonDate = $start_date->format('Y-m-d');

        if ($currentDate !== $lessonDate) {
            if ($currentDate !== '') {
                $lessonHTML .= '</div>';
            }
            $lessonHTML .= '<div class="lesson-panel">';
            $lessonHTML .= '<div class="lesson-header">';
            $lessonHTML .= '<p class="lesson-date">' . $lessonDate . '</p>';
            $lessonHTML .= '<hr class="divider-line"/>';
            $lessonHTML .= '</div>';
            $currentDate = $lessonDate;
        }

        $lessonHTML .= '<div class="lesson">';
        $lessonHTML .= '<div class="lesson-title">';
        $lessonHTML .= '<p>' . htmlspecialchars($row['tytul']) . '</p>';
        $lessonHTML .= '</div>';
        $lessonHTML .= '<div class="lesson-time">';
        $lessonHTML .= '<p>' . $start_date->format('H:i') . ' - ' . $end_date->format('H:i') . '</p>';
        $lessonHTML .= '</div>';
        $lessonHTML .= '</div>';
    }

    $lessonHTML .= '</div>';
    $lessonHTML .= '</section>';
} else {
    $lessonHTML = '<p>Brak zajęć w wybranym tygodniu.</p>';
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>Plan ZUT</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<main>
    <aside class="sidebar">
        <img class="logo" alt="logo" src="images/logo.png"/>

        <label>
            <h2>FILTERS</h2>
            <input class="formInput" placeholder="wykładowca">
            <input class="formInput" placeholder="klasa">
            <input class="formInput" placeholder="numer grupy">
            <input class="formInput" placeholder="numer indeksu">
            <input class="formInput" placeholder="zajęcia">
            <div>
                <button class="formInput">
                    <img alt="check" src="images/check.svg"/>
                    <p>ACCEPT</p>
                </button>
                <button class="formInput">
                    <img alt="clear" src="images/clear.svg"/>
                    <p>CLEAR</p>
                </button>
            </div>
        </label>
        <div class="option">
            <div>
                <img alt="font size" src="images/Aa.svg"/>
                <div class="textSize">
                    <p>SMALL</p>
                    <p>MEDIUM</p>
                    <p>BIG</p>
                </div>
            </div>
            <div>
                <button class="darkMode-btn">
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
        <?php echo $lessonHTML; ?>
    </section>



</main>
</body>
</html>
