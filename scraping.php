<?php
function dbConnection(){       //funkcja do łączenia się z bazą

    $host = 'localhost';
    $username = 'root';
    $password = '';
    $dbname = 'project';

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);

        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $pdo;
    } catch (PDOException $e) {
        die("ERROR conecting: " . $e->getMessage());
    }
}

// Funkcja pobierania odpowiedzi z API z opcjonalną obsługą SSL
function fetchAPIResponse($url, $ssl_error = false)
{
    $context = null;
    if ($ssl_error) {
        $options = [
            "ssl" => [
                "verify_peer" => false,
                "verify_peer_name" => false,
            ],
            "http" => [
                "timeout" => 30  // Zwiększony timeout
            ]
        ];
        $context = stream_context_create($options);
    }

    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
        echo "Błąd: nie udało się pobrać danych z URL: $url\n";
        return null;
    }

    return $response;
}

// Uniwersalna funkcja do pobierania danych z API i zapisywania do pliku CSV
function fetchAndSaveToCSV($pdo, $url, $csvFilename, $headers, $processDataCallback, $dbColumns, $tableName, $ssl_error = false)
{
    try {
        $context = null;
        if ($ssl_error) {
            $options = [
                "ssl" => [
                    "verify_peer" => false,
                    "verify_peer_name" => false,
                ],
            ];
            $context = stream_context_create($options);
        }

        $response = file_get_contents($url, false, $context);
        echo "Pomyślnie otrzymano dane z API: $url\n";

        $data = json_decode($response, true);
        if (!$data) {
            echo "Błąd: Nie udało się dekodować danych z API.\n";
            return;
        }

        $csvData = [$headers];
        $processedData = $processDataCallback($data);
        $csvData = array_merge($csvData, $processedData);

        $file = fopen($csvFilename, 'w');
        foreach ($csvData as $row) {
            fputcsv($file, $row);
        }
        fclose($file);

        echo "Dane zapisano do pliku: $csvFilename\n";

        if (!empty($dbColumns) && $pdo && $tableName) {
            $insertQuery = "INSERT INTO $tableName (" . implode(', ', $dbColumns) . ") VALUES (" . implode(', ', array_fill(0, count($dbColumns), '?')) . ")";
            $statement = $pdo->prepare($insertQuery);

            foreach ($processedData as $row) {
                if ($tableName == 'sala') {
                    // Пошук або додавання `wydzialID` для `sala`
                    list($wydzialNazwa, $pokoj) = $row;

                    $wydzialID = getIDFromTable($pdo, "wydzial", ["nazwa" => $wydzialNazwa], "wydzialID");
                    if (!$wydzialID) {
                        $insertWydzialStmt = $pdo->prepare("INSERT INTO wydzial (nazwa) VALUES (:nazwa)");
                        $insertWydzialStmt->execute([':nazwa' => $wydzialNazwa]);
                        $wydzialID = $pdo->lastInsertId();
                    }

                    $row = [$wydzialID, $pokoj];
                }

                try {
                    $statement->execute($row);
                } catch (PDOException $e) {
                    echo "Błąd podczas dodawania danych do bazy danych: " . $e->getMessage() . "\n";
                }
            }
            echo "Dane zapisano do bazy w tabeli: $tableName\n";
        }

    } catch (Exception $e) {
        echo "Błąd podczas scrapowania: " . $e->getMessage() . "\n";
        exit();
    }
}

// Przetwarzanie danych dla wykładowców
function processWykladowcaData($data)
{
    $result = [];
    foreach ($data as $person) {
        if (isset($person['item']) && strpos($person['item'], " ") !== false) {
            list($surname, $name) = explode(" ", $person['item'], 2);
            $result[] = [$name, $surname];
        }
    }
    return $result;
}

// Przetwarzanie danych dla wydziałów
function processWydzialData($data)
{
    $wydzialArray = [];
    foreach ($data as $department) {
        if (isset($department['item']) && strpos($department['item'], " ") !== false) {
            $nameParts = explode(" ", $department["item"]);
            $wydzialArray[] = [$nameParts[0]];
        }
    }
    return array_unique($wydzialArray, SORT_REGULAR);
}

function insertSalaWithWydzial(PDO $pdo, string $wydzialNazwa, string $pokoj): void
{
    try {
        $stmt = $pdo->prepare("SELECT wydzialID FROM wydzial WHERE nazwa = :nazwa");
        $stmt->execute([':nazwa' => $wydzialNazwa]);
        $wydzial = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$wydzial) {
            $stmt = $pdo->prepare("INSERT INTO wydzial (nazwa) VALUES (:nazwa)");
            $stmt->execute([':nazwa' => $wydzialNazwa]);
            $wydzialID = $pdo->lastInsertId();
        } else {
            $wydzialID = $wydzial['wydzialID'];
        }

        $stmt = $pdo->prepare("INSERT INTO sala (wydzialID, pokoj) VALUES (:wydzialID, :pokoj)");
        $stmt->execute([
            ':wydzialID' => $wydzialID,
            ':pokoj' => $pokoj
        ]);


    } catch (PDOException $e) {
        echo "Ошибка: " . $e->getMessage() . "\n";
    }
}

// Przetwarzanie danych dla sal
function processSalaData($data)
{
    $result = [];
    foreach ($data as $room) {
        if (isset($room['item']) && strpos($room['item'], " ") !== false) {
            list($wydzialNazwa, $pokoj) = explode(" ", $room['item'], 2);
            $result[] = [$wydzialNazwa, $pokoj];
        }
    }
    return $result;
}
function scrapAndFilterNumerAlbumu($pdo, $ssl_error=False, $addToBase=True) {
    try {
        $url = 'https://plan.zut.edu.pl/schedule_student.php?number={album_index}&start=2024-10-01T00%3A00%3A00%2B01%3A00&end=2025-11-01T00%3A00%3A00%2B01%3A00';

        for ($album_index = 59623; $album_index >= 1; $album_index--) {
            $url_replaced = str_replace('{album_index}', $album_index, $url);
            if ($ssl_error) {
                $options = [
                    "ssl" => [
                        "verify_peer" => false,
                        "verify_peer_name" => false,
                    ],
                ];
                $context = stream_context_create($options);
                $response = file_get_contents($url_replaced, false, $context);
            } else {
                $response = file_get_contents($url_replaced);
            }

            $data = json_decode($response, true);

            if (count($data) > 1) {
                break;
            }
        }

        $the_largest_index = $album_index;

        if ($addToBase) {
            $sqlInsert = "INSERT INTO numerAlbumu (numer) VALUES (:numerAlbumu)";
            $statement = $pdo->prepare($sqlInsert);
            $pdo->beginTransaction();
            try {
                for ($index = 1; $index <= $the_largest_index; $index++) {
                    $statement->bindParam(':numerAlbumu', $index, PDO::PARAM_INT);
                    $statement->execute();
                }
                $pdo->commit();
            } catch (PDOException $e) {
                $pdo->rollBack();
                exit();
            }
        }

        $sql = "SELECT numer FROM numerAlbumu";
        $statement = $pdo->prepare($sql);
        $statement->execute();
        $numerAlbumu = $statement->fetchAll(PDO::FETCH_ASSOC);

        $deleteStatement = $pdo->prepare("DELETE FROM numerAlbumu WHERE numer = :numer");

        for ($i = 0; $i < count($numerAlbumu); $i++) {
            $url_replaced = str_replace('{album_index}', $numerAlbumu[$i]['numer'], $url);

            if ($ssl_error) {
                $options = [
                    "ssl" => [
                        "verify_peer" => false,
                        "verify_peer_name" => false,
                    ],
                ];
                $context = stream_context_create($options);
                $response = file_get_contents($url_replaced, false, $context);
            } else {
                $response = file_get_contents($url_replaced);
            }

            $data = json_decode($response, true);

            if (count($data) == 1) {
                $deleteStatement->bindParam(':numer', $numerAlbumu[$i]['numer'], PDO::PARAM_INT);
                $deleteStatement->execute();
            }
        }

    } catch (PDOException $e) {
        exit();
    }
}


// Funkcje do scrapowania poszczególnych danych
function scrapDataWykladowca($pdo)
{
    $url = 'https://plan.zut.edu.pl/schedule.php?kind=teacher&query=';
    fetchAndSaveToCSV($pdo, $url, 'wykladowcy.csv', ['imie', 'nazwisko'], 'processWykladowcaData', ['Imie', 'Nazwisko'], 'wykladowca');
}

function scrapDataWydzial($pdo)
{
    $url = 'https://plan.zut.edu.pl/schedule.php?kind=room&query=';
    fetchAndSaveToCSV($pdo, $url, 'wydzial.csv', ['nazwa'], 'processWydzialData', ['Nazwa'], 'wydzial');
}

function scrapDataSala($pdo)
{
    $url = 'https://plan.zut.edu.pl/schedule.php?kind=room&query=';
    $data = fetchAPIResponse($url, false);
    if (!$data) {
        echo "Błąd pobierania danych sal z API.\n";
        return;
    }
    $parsedData = json_decode($data, true);
    if (!$parsedData) {
        echo "Błąd parsowania danych JSON.\n";
        return;
    }
    $processedData = processSalaData($parsedData);
    foreach ($processedData as $row) {
        insertSalaWithWydzial($pdo, $row[0], $row[1]);
    }
    echo "Przetwarzanie i zapis sal zakończony.\n";

}


function scrapPrzedmiot(PDO $pdo, $ssl_error = false) {
    try {
        $stmt = $pdo->prepare("
            SELECT w.nazwa AS wydzial_nazwa, s.pokoj 
            FROM sala s 
            JOIN wydzial w ON s.wydzialID = w.wydzialID
        ");
        $stmt->execute();
        $rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rooms as $room) {
            $url = 'https://plan.zut.edu.pl/schedule_student.php?room=';
            $link = str_replace(" ", "%20", trim($room["wydzial_nazwa"] . " " . $room["pokoj"]));

            $dateNow = (new DateTime())->format("Y-m-d");
            $dateMonth = (new DateTime())->modify("+1 month")->format("Y-m-d");

            $url .= $link . "&start=" . $dateNow . "T00%3A00%3A00%2B01%3A00&end=" . $dateMonth . "T00%3A00%3A00%2B01%3A00";

            $response = fetchAPIResponse($url, $ssl_error);
            $data = json_decode($response, true);

            foreach ($data as $przedmiot) {
                if (isset($przedmiot["subject"])) {
                    $subjectName = $przedmiot["subject"];

                    $existingSubjectID = getIDFromTable($pdo, "przedmiot", ["nazwa" => $subjectName], "przedmiotID");
                    if (!$existingSubjectID) {
                        $stmtInsert = $pdo->prepare("INSERT INTO przedmiot (nazwa) VALUES (:nazwa)");
                        $stmtInsert->bindParam(':nazwa', $subjectName, PDO::PARAM_STR);
                        try {
                            $stmtInsert->execute();
                        } catch (PDOException $e) {
                        }
                    }
                }
            }
        }

        echo "Dane przedmiots zapisano do pliku.\n";

    } catch (Exception $e) {
        echo "Błąd podczas scrapowania przedmiots : " . $e->getMessage() . "\n";
    }
}


function scrapGrupy($type, $value, $csvFilename = 'grupy.csv', $ssl_error = false, $clearTableCondition = true)
{
    try {
        // Wczytanie istniejących grup z CSV
        $existingGroups = [];
        if (file_exists($csvFilename) && ($handle = fopen($csvFilename, "r")) !== false) {
            fgetcsv($handle);  // Pominięcie nagłówka
            while (($row = fgetcsv($handle)) !== false) {
                $existingGroups[] = $row[0];
            }
            fclose($handle);
        }

        if ($clearTableCondition) {
            $existingGroups = [];  // Wyczyszczenie listy grup
        }

        $groupsArray = [];

        // Ustawienie zakresu dat (1 miesiąc do przodu)
        $dateNow = (new DateTime())->format("Y-m-d");
        $dateMonth = (new DateTime())->modify("+1 month")->format("Y-m-d");

        // Przygotowanie URL w zależności od typu
        $formattedValue = str_replace(" ", "%20", trim($value));
        $url = "https://plan.zut.edu.pl/schedule_student.php?{$type}={$formattedValue}&start={$dateNow}T00%3A00%3A00%2B01%3A00&end={$dateMonth}T00%3A00%3A00%2B01%3A00";

        echo "Pobieranie danych z API: $url\n";

        $response = fetchAPIResponse($url, $ssl_error);
        if (is_null($response)) {
            echo "Błąd połączenia z API dla wartości: $value\n";
            return;
        }

        $data = json_decode($response, true);

        // Przetwarzanie danych z API
        foreach ($data as $group) {
            if (isset($group["group_name"])) {
                $groupsArray[] = $group["group_name"];
            }
        }

        // Scalanie nowych i istniejących grup
        $allGroups = array_unique(array_merge($existingGroups, $groupsArray));

        // Zapis do pliku CSV
        $file = fopen($csvFilename, 'w');
        fputcsv($file, ["nazwa"]);  // Nagłówek CSV
        foreach ($allGroups as $group) {
            fputcsv($file, [$group]);
        }
        fclose($file);

        echo "Dane grup zapisano do pliku: $csvFilename\n";

    } catch (Exception $e) {
        echo "Błąd podczas scrapowania grup: " . $e->getMessage() . "\n";
        exit();
    }
}

function scrapGrupyTest($pdo, $subject = 'test', $ssl_error = false)
{
    try {
        // Przygotowanie dat (zakres: 1 miesiąc do przodu)
        $dateNow = (new DateTime())->format("Y-m-d");
        $dateMonth = (new DateTime())->modify("+1 month")->format("Y-m-d");

        // Tworzenie URL do API
        $subject = str_replace(" ", "%20", $subject);  // Zamiana spacji na %20
        $url = "https://plan.zut.edu.pl/schedule_student.php?subject={$subject}&start={$dateNow}T00%3A00%3A00%2B01%3A00&end={$dateMonth}T00%3A00%3A00%2B01%3A00";
        echo "Połączenie z API: $url\n";

        // Pobranie danych z API
        $response = fetchAPIResponse($url, $ssl_error);
        if (is_null($response)) {
            echo "Błąd połączenia z API.\n";
            return;
        }

        $data = json_decode($response, true);
        $groupsArray = [];
        $processedCount = 0;

        // Przetwarzanie danych z API
        foreach ($data as $group) {
            if (isset($group["group_name"])) {
                $groupsArray[] = trim($group["group_name"]); // Trimowanie i dodanie nazw grup do tablicy
            }
        }

        // Usuwanie duplikatów w obrębie API
        $groupsArray = array_unique($groupsArray);

        if (empty($groupsArray)) {
            echo "Brak danych dla grup.\n";
            return;
        }

        // Pobieranie istniejących grup z bazy danych
        $existingGroups = $pdo->query("SELECT nazwa FROM grupa")->fetchAll(PDO::FETCH_COLUMN);
        $existingGroups = array_map('trim', $existingGroups);
        $newGroups = array_diff($groupsArray, $existingGroups);

        // Przygotowanie zapytania SQL do zapisania danych w bazie
        $insertQuery = "INSERT INTO grupa (nazwa) VALUES (:nazwa)";
        $statement = $pdo->prepare($insertQuery);

        foreach ($newGroups as $group) {
            try {
                // Wstawianie grupy do bazy
                $statement->execute([':nazwa' => $group]);
                $processedCount++;
                echo "Dodano grupę: $group\n";
            } catch (PDOException $e) {
                echo "Błąd podczas dodawania grupy '$group': " . $e->getMessage() . "\n";
            }
        }

        // Wyświetlenie liczby przetworzonych grup
        echo "Łącznie przetworzono grup: $processedCount\n";

    } catch (Exception $e) {
        // Obsługa błędów
        echo "Błąd podczas pracy z API lub bazą danych: " . $e->getMessage() . "\n";
    }
}

function scrapZajecia($pdo, $type, $value, $csvFilename = 'lekcje.csv', $ssl_error = false, $addToBase = true)
{
    try {
        $dateNow = (new DateTime())->format("Y-m-d");
        $dateMonth = (new DateTime())->modify("+1 month")->format("Y-m-d");

        $formattedValue = str_replace(" ", "%20", trim($value));
        $url = "https://plan.zut.edu.pl/schedule_student.php?{$type}={$formattedValue}&start={$dateNow}T00%3A00%3A00%2B01%3A00&end={$dateMonth}T00%3A00%3A00%2B01%3A00";

        echo "URL: $url\n";

        $response = fetchAPIResponse($url, $ssl_error);
        if (is_null($response)) {
            return;
        }

        $data = json_decode($response, true);
        $lessonsArray = [];

        foreach ($data as $lesson) {
            if (isset($lesson["worker"], $lesson["group_name"], $lesson["room"], $lesson["subject"])) {
                $lessonsArray[] = [
                    "worker" => $lesson["worker"],
                    "group_name" => $lesson["group_name"],
                    "room" => $lesson["room"],
                    "subject" => $lesson["subject"],
                    "title" => $lesson["title"] ?? "",
                    "description" => $lesson["description"] ?? "",
                    "start" => $lesson["start"],
                    "end" => $lesson["end"],
                    "lesson_form" => $lesson["lesson_form"] ?? "",
                ];
            }
        }

        if (empty($lessonsArray)) {
            return;
        }

        $file = fopen($csvFilename, 'w');
        fputcsv($file, ["worker", "group_name", "room", "subject", "title", "description", "start", "end", "lesson_form"]); // Заголовки CSV
        foreach ($lessonsArray as $lesson) {
            fputcsv($file, $lesson);
        }
        fclose($file);

        echo "SAVED IN : $csvFilename\n";

        if ($addToBase) {
            $existingLessons = $pdo->query("
                SELECT 
                    w.Imie || ' ' || w.Nazwisko as worker, 
                    g.nazwa as group_name, 
                    CONCAT(wyd.nazwa, ' ', s.pokoj) as room, 
                    p.nazwa as subject, 
                    l.tytul as title, 
                    l.opis as description, 
                    l.start, 
                    l.koniec as end, 
                    l.formaZajec as lesson_form
                FROM 
                    lekcja l
                JOIN wykladowca w ON l.wykladowcaID = w.wykladowcaID
                JOIN grupa g ON l.grupaID = g.grupaID
                JOIN sala s ON l.salaID = s.salaID
                JOIN wydzial wyd ON s.wydzialID = wyd.wydzialID
                JOIN przedmiot p ON l.przedmiotID = p.przedmiotID
            ")->fetchAll(PDO::FETCH_ASSOC);

            $existingKeys = array_map(fn($key) => "{$key['worker']}-{$key['group_name']}-{$key['room']}-{$key['subject']}-{$key['title']}-{$key['description']}-{$key['start']}-{$key['end']}-{$key['lesson_form']}", $existingLessons);

            $statement = $pdo->prepare("
                INSERT INTO lekcja (wykladowcaID, grupaID, salaID, przedmiotID, tytul, opis, start, koniec, formaZajec) 
                VALUES (:wykladowcaID, :grupaID, :salaID, :przedmiotID, :tytul, :opis, :start, :koniec, :formaZajec)
            ");

            foreach ($lessonsArray as $lesson) {
                $uniqueKey = "{$lesson["worker"]}-{$lesson["group_name"]}-{$lesson["room"]}-{$lesson["subject"]}-{$lesson["title"]}-{$lesson["description"]}-{$lesson["start"]}-{$lesson["end"]}-{$lesson["lesson_form"]}";

                if (in_array($uniqueKey, $existingKeys)) {
                    continue;
                }

                list($lastName, $firstName) = explode(" ", trim($lesson["worker"]), 2);
                $wykladowcaID = getIDFromTable($pdo, "wykladowca", ["Imie" => $firstName, "Nazwisko" => $lastName], "wykladowcaID");

                $grupaID = getIDFromTable($pdo, "grupa", ["nazwa" => $lesson["group_name"]], "grupaID");

                list($facultyName, $roomName) = explode(" ", trim($lesson["room"]), 2);
                $salaID = getIDFromTable($pdo, "sala", ["pokoj" => $roomName, "nazwa" => $facultyName], "salaID", "JOIN wydzial w ON sala.wydzialID = w.wydzialID");

                $przedmiotID = getIDFromTable($pdo, "przedmiot", ["nazwa" => $lesson["subject"]], "przedmiotID");

                if (!$wykladowcaID || !$grupaID || !$salaID || !$przedmiotID) {
                    continue;
                }

                $statement->bindParam(':wykladowcaID', $wykladowcaID, PDO::PARAM_INT);
                $statement->bindParam(':grupaID', $grupaID, PDO::PARAM_INT);
                $statement->bindParam(':salaID', $salaID, PDO::PARAM_INT);
                $statement->bindParam(':przedmiotID', $przedmiotID, PDO::PARAM_INT);
                $statement->bindParam(':tytul', $lesson["title"], PDO::PARAM_STR);
                $statement->bindParam(':opis', $lesson["description"], PDO::PARAM_STR);
                $statement->bindParam(':start', $lesson["start"], PDO::PARAM_STR);
                $statement->bindParam(':koniec', $lesson["end"], PDO::PARAM_STR);
                $statement->bindParam(':formaZajec', $lesson["lesson_form"], PDO::PARAM_STR);

                // Выполняем вставку
                try {
                    $statement->execute();
                } catch (PDOException $e) {
                    echo "ERROR INSERT: " . $e->getMessage() . "\n";
                }
            }
        }
    } catch (PDOException $e) {
        echo "ERROR API: " . $e->getMessage() . "\n";
    }
}

function getIDFromTable(PDO $pdo, string $table, array $conditions, string $columnID, string $join = ""): ?string {


    $whereClauses = array_map(fn($key) => "$key = :$key", array_keys($conditions));
    $query = "SELECT $columnID FROM $table $join WHERE " . implode(" AND ", $whereClauses);

    try {
        $statement = $pdo->prepare($query);

        foreach ($conditions as $key => $value) {
            $statement->bindValue(":$key", $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }

        $statement->execute();

        $result = $statement->fetch(PDO::FETCH_ASSOC);

        return $result[$columnID] ?? null;
    } catch (PDOException $e) {
        echo "ERROR SQL: " . $e->getMessage() . "\n";
        echo "REQUEST: $query\n";
        echo "PARAMETRS: " . print_r($conditions, true) . "\n";
        return null;
    }
}

function scrapAlbumGrupy($pdo, $albumNumber, $ssl_error = false, $addToBase = true)
{
    try {
        // Przygotowanie zakresu dat (1 miesiąc do przodu)
        $dateNow = (new DateTime())->format("Y-m-d");
        $dateMonth = (new DateTime())->modify("+1 month")->format("Y-m-d");

        // Przygotowanie URL do API
        $url = "https://plan.zut.edu.pl/schedule_student.php?number={$albumNumber}&start={$dateNow}T00%3A00%3A00%2B01%3A00&end={$dateMonth}T00%3A00%3A00%2B01%3A00";


        // Pobieranie danych z API
        $response = fetchAPIResponse($url, $ssl_error);
        if (is_null($response)) {
            echo "Błąd połączenia z API dla numeru albumu: $albumNumber\n";
            return;
        }
        $data = json_decode($response, true);

        // Przetwarzanie danych z API - lista grup
        $groupsArray = array_unique(array_column(array_filter($data, fn($group) => isset($group["group_name"])), "group_name"));
        if (empty($groupsArray)) {
            //echo "Brak grup dla numeru albumu: $albumNumber\n";
            return;
        }

        // Pobranie istniejących rekordów numer-album-grupa z bazy danych
        $existingRecords = $pdo->query("
            SELECT g.nazwa, na.numer 
            FROM NumerAlbumuGrupa n
            JOIN grupa g ON n.grupaID = g.grupaID
            JOIN numerAlbumu na ON n.numerAlbumuID = na.numerAlbumuID
        ")->fetchAll(PDO::FETCH_ASSOC);

        $existingKeys = array_map(fn($record) => "{$record['nazwa']}-{$record['numer']}", $existingRecords);

        // Tworzenie listy nowych rekordów do dodania
        $newGroups = array_filter($groupsArray, function ($groupName) use ($albumNumber, $existingKeys) {
            $uniqueKey = "{$groupName}-{$albumNumber}";
            return !in_array($uniqueKey, $existingKeys);
        });

        if (empty($newGroups)) {
            echo "Brak nowych grup do dodania dla numeru albumu: $albumNumber\n";
            return;
        }

        // Wstawianie nowych rekordów do bazy danych
        if ($addToBase) {
            $insertStatement = $pdo->prepare("
                INSERT INTO NumerAlbumuGrupa (grupaID, numerAlbumuID) 
                VALUES (:grupaID, :numerAlbumuID)
            ");

            foreach ($newGroups as $groupName) {
                $groupID = getIDFromTable($pdo, "grupa", ["nazwa" => $groupName], "grupaID");
                if (!$groupID) {
                    //echo "Błąd: nie znaleziono grupy '$groupName' w bazie danych.\n";
                    continue;
                }

                $insertStatement->bindParam(':grupaID', $groupID, PDO::PARAM_INT);
                $insertStatement->bindParam(':numerAlbumuID', $albumNumber, PDO::PARAM_INT);

                try {
                    $insertStatement->execute();
                } catch (PDOException $e) {
                    echo "Błąd zapytania INSERT: " . $e->getMessage() . "\n";
                }
            }
        }
    } catch (PDOException $e) {
        echo "Błąd połączenia z bazą danych: " . $e->getMessage() . "\n";
    }
}
function processAllAlbumNumbers($pdo, $ssl_error = false)
{
    try {
        $query = "SELECT numer FROM numeralbumu";
        $statement = $pdo->prepare($query);
        $statement->execute();
        $numerList = $statement->fetchAll(PDO::FETCH_COLUMN);

        if (empty($numerList)) {
            echo "Brak danych w tabeli numeralbumu.\n";
            return;
        }

        foreach ($numerList as $numer) {
            scrapAlbumGrupy($pdo, $numer, $ssl_error);
        }

        echo "Przetwarzanie wszystkich numerów albumu zakończone.\n";

    } catch (Exception $e) {
        echo "Błąd podczas przetwarzania numerów albumów: " . $e->getMessage() . "\n";
    }
}
function scrapZajeciaF(PDO $pdo)
{
    try {
        $query = "SELECT numer FROM numeralbumu";
        $statement = $pdo->query($query);
        $numerAlbumuList = $statement->fetchAll(PDO::FETCH_COLUMN);

        if (empty($numerAlbumuList)) {
            return;
        }

        foreach ($numerAlbumuList as $numer) {
            //printf("Processing numer: %s\n", $numer);
            try {
                scrapZajecia(
                    $pdo,
                    'number',
                    $numer,
                    'lekcje.csv',
                    false,
                    true
                );
                echo "Finish for numer: $numer\n";
            } catch (Exception $e) {
                echo "ERROR for number $numer: " . $e->getMessage() . "\n";
            }
        }

        echo "Przetwarzanie zakończone dla wszystkich numerów w `numeralbumu`.\n";
    } catch (PDOException $e) {
        echo "Błąd podczas pracy z tabelą numeralbumu: " . $e->getMessage() . "\n";
    }
}

$pdo = dbConnection();
set_time_limit(0);  // Skrypt może działać bez ograniczeń czasowych

// Wywołania scrapowania

//scrapDataWykladowca( $pdo);
//scrapDataWydzial($pdo);
scrapDataSala($pdo);
//scrapPrzedmiot($pdo);

//scrapAndFilterNumerAlbumu($pdo);
//scrapGrupyTest($pdo);
//processAllAlbumNumbers($pdo, true);

//scrapZajecia($pdo, 'number', '53733', 'lekcje_albumu.csv', true);
scrapZajeciaF($pdo);