<?php
session_start();

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "test";

// Pobranie numeru albumu z sesji
$numerIndeksu = $_SESSION['numerIndeksu'];

// Pobranie danych przesłanych przez JavaScript
$data = json_decode(file_get_contents('php://input'), true);
$view = isset($data['view']) ? $data['view'] : 'day';
$selectedDate = isset($data['date']) && !empty($data['date']) ? $data['date'] : date('Y-m-d'); // Domyślnie bieżąca data

try {
    // Połączenie z bazą danych
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    // Obliczanie zakresu dat
    $selectedTimestamp = strtotime($selectedDate);
    if ($view === 'week') {
        $startOfWeek = date('Y-m-d', strtotime('monday this week', $selectedTimestamp));
        $endOfWeek = date('Y-m-d', strtotime('sunday this week', $selectedTimestamp));
    } else {
        $startOfWeek = $selectedDate;
        $endOfWeek = $selectedDate;
    }

    // Budowanie zapytania SQL z sortowaniem
    $query = "SELECT DISTINCT lekcja.tytul AS tytul, lekcja.start AS start, lekcja.koniec AS koniec,
                     wykladowca.nazwisko AS wyk_nazwisko, wykladowca.imie AS wyk_imie, sala.pokoj AS sala
              FROM lekcja
              INNER JOIN wykladowca ON lekcja.wykladowcaID = wykladowca.wykladowcaID
              INNER JOIN sala ON lekcja.salaID = sala.salaID
              INNER JOIN grupa ON lekcja.grupaID = grupa.grupaID
              INNER JOIN numeralbumugrupa ON lekcja.grupaID = numeralbumugrupa.grupaID
              WHERE lekcja.start BETWEEN ? AND ?
              AND numeralbumugrupa.numerAlbumuID = ?
              ORDER BY lekcja.start ASC";

    $stmt = $conn->prepare($query);
    $startTime = $startOfWeek . 'T00:00:00';
    $endTime = $endOfWeek . 'T23:59:59';
    $stmt->bind_param('sss', $startTime, $endTime, $numerIndeksu);
    $stmt->execute();
    $result = $stmt->get_result();

    // Generowanie HTML dla zajęć
    $lessonHTML = '';
    $currentDate = '';

    if ($result->num_rows > 0) {
        $lessonHTML .= '<section class="lesson-panel-container">';
        $lessonHTML .= '<div class="lesson-panels">';

        while ($row = $result->fetch_assoc()) {
            $start_date = new DateTime($row['start']);
            $end_date = isset($row['koniec']) ? new DateTime($row['koniec']) : $start_date;
            $lessonDate = $start_date->format('Y-m-d');

            // Nowa sekcja dla innej daty
            if ($currentDate !== $lessonDate) {
                if ($currentDate !== '') {
                    $lessonHTML .= '</div>'; // Zamknięcie poprzedniej sekcji
                }
                $lessonHTML .= '<div class="lesson-panel">';
                $lessonHTML .= '<div class="lesson-header">';
                $lessonHTML .= '<p class="lesson-date">' . $lessonDate . '</p>';
                $lessonHTML .= '<hr class="divider-line"/>';
                $lessonHTML .= '</div>';
                $currentDate = $lessonDate;
            }

            // Dodanie lekcji do kolumny
            $lessonHTML .= '<div class="lesson">';
            $lessonHTML .= '<div class="lesson-title">';
            $lessonHTML .= '<p>' . htmlspecialchars($row['tytul'], ENT_QUOTES, 'UTF-8') . '</p>';
            $lessonHTML .= '</div>';
            $lessonHTML .= '<div class="lesson-time">';
            $lessonHTML .= '<p>' . $start_date->format('H:i') . ' - ' . $end_date->format('H:i') . '</p>';
            $lessonHTML .= '</div>';
            $lessonHTML .= '<div class="lesson-location">';
            $lessonHTML .= '<p>Sala: ' . htmlspecialchars($row['sala'], ENT_QUOTES, 'UTF-8') . '</p>';
            $lessonHTML .= '</div>';
            $lessonHTML .= '<div class="lesson-teacher">';
            $lessonHTML .= '<p>Wykładowca: ' . htmlspecialchars($row['wyk_imie'] . ' ' . $row['wyk_nazwisko'], ENT_QUOTES, 'UTF-8') . '</p>';
            $lessonHTML .= '</div>';
            $lessonHTML .= '</div>';
        }

        $lessonHTML .= '</div>'; // Zamknięcie ostatniej kolumny
        $lessonHTML .= '</div>'; // Zamknięcie kontenera paneli
        $lessonHTML .= '</section>';
    } else {
        $lessonHTML = '<p>Brak zajęć w wybranym okresie dla numeru albumu: ' . htmlspecialchars($numerIndeksu) . '</p>';
    }

    echo $lessonHTML;
    $stmt->close();
} catch (Exception $e) {
    echo '<p>Błąd: ' . htmlspecialchars($e->getMessage()) . '</p>';
}
?>
