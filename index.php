<?php

function generateBarcode() {
    return substr(str_shuffle('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 10);
}

function callApi($url, $data): mixed {
    $options = [
        'http' => [
            'header'  => "Content-Type: application/json\r\n",
            'method'  => 'POST',
            'content' => json_encode($data),
        ],
    ];
    $context  = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    return json_decode($result, true);
}

function addOrder($event_id, $event_date, $ticket_adult_price, $ticket_adult_quantity, $ticket_kid_price, $ticket_kid_quantity) {
    $equal_price = ($ticket_adult_price * $ticket_adult_quantity) + ($ticket_kid_price * $ticket_kid_quantity);
    $barcode = generateBarcode();

    do {
        $bookingResponse = callApi('https://api.site.com/book', [
            'event_id' => $event_id,
            'event_date' => $event_date,
            'ticket_adult_price' => $ticket_adult_price,
            'ticket_adult_quantity' => $ticket_adult_quantity,
            'ticket_kid_price' => $ticket_kid_price,
            'ticket_kid_quantity' => $ticket_kid_quantity,
            'barcode' => $barcode,
        ]);

        if (isset($bookingResponse['error']) && $bookingResponse['error'] === 'barcode уже существет!') {
            $barcode = generateBarcode();
        } else {
            break;
        }
    } while (true);

    $approval = callApi('https://api.site.com/approve', [
        'barcode' => $barcode,
    ]);

    if (isset($approval['message']) && $approval['message'] === 'Заказ успешно обработан') {
        $mysqli = new mysqli("localhost", "username", "password", "database");

        if ($mysqli->connect_error) {
            die("Connection failed: " . $mysqli->connect_error);
        }

        $event_id = $mysqli->real_escape_string($event_id);
        $event_date = $mysqli->real_escape_string($event_date);
        $barcode = $mysqli->real_escape_string($barcode);

        $sql = "INSERT INTO orders (event_id, event_date, ticket_adult_price, ticket_adult_quantity, ticket_kid_price, ticket_kid_quantity, barcode, equal_price, created) 
                VALUES ('$event_id', '$event_date', $ticket_adult_price, $ticket_adult_quantity, $ticket_kid_price, $ticket_kid_quantity, '$barcode', $equal_price, NOW())";

        if ($mysqli->query($sql) === TRUE) {
            echo "Заказ успешно добавлен.";
        } else {
            echo "Error: " . $sql . "<br>" . $mysqli->error;
        }

        $mysqli->close();
    } else {
        echo "Error in approval: " . json_encode($approval);
    }
}
