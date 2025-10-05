<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Document</title>
</head>
<body>
    <h1>🎫 New Ticket Booking</h1>
<p>Name: {{ $ticket->name }} {{ $ticket->last_name }}</p>
<p>Trip ID: {{ $trip->id }}</p>
<p>Seats: {{ implode(', ', $ticket->seat_numbers) }}</p>
<p>Phone: {{ $ticket->phone }}</p>

</body>
</html>