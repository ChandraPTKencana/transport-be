<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Map Screenshot</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        #map {
            width: 800px;
            height: 600px;
        }
    </style>
</head>
<body>

    <div id="map"></div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // Get latitude & longitude from URL parameters
        const urlParams = new URLSearchParams(window.location.search);
        // const lat = urlParams.get('lat') || 40.7128;
        // const lng = urlParams.get('lng') || -74.0060;
        const lat = 3.704385;
        const lng = 98.660519;
        const address = urlParams.get('address') || "Unknown Location";

        // Initialize map
        var map = L.map('map').setView([lat, lng], 15);

        // Add OpenStreetMap tiles
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(map);

        // Add Marker
        L.marker([lat, lng]).addTo(map)
            .bindPopup("<b>Location:</b> " + address)
            .openPopup();
    </script>

</body>
</html>
