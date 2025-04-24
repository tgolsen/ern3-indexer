<?php
// Define the Solr URL
$solr_url = 'http://solr:8983/solr/ern3/select?indent=true&q.op=OR';

// Initialize the query parameters
$query_filters = [];
if (!empty($_GET['artist_name'])) {
    $query_filters[] = 'artist_name:' . '"' . $_GET['artist_name'] . '"';
}
if (!empty($_GET['genre'])) {
    $query_filters[] = 'genre:' . '"' . $_GET['genre'] . '"';
}
if (!empty($_GET['title'])) {
    $query_filters[] = 'title:' . '"' . $_GET['title'] . '"';
}
if (!empty($_GET['label_name'])) {
    $query_filters[] = 'label_name:' . '"' . $_GET['label_name'] . '"';
}

// Check the state of the "Include results without images" checkbox
$include_no_images = isset($_GET['include_no_images']) && $_GET['include_no_images'] === 'on';
if ($include_no_images) {
    $include_image_filter = '';} else{
    // Add filter to include only results where image_url is non-empty
    $include_image_filter = ' AND image_url:[* TO *]';
}

// Combine all filters into the final Solr query
if (count($query_filters) > 0) {
    // If there are user-defined filters, combine them with `AND`
    $query = implode(' AND ', $query_filters);
} else {
    // If no filters are provided, use the default query `*:*`
    $query = '*:*';
}

// Add image filter
$query .= $include_image_filter;

// Pagination Variables
$rows_per_page = 10; // Number of records per page
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) $current_page = 1;
$start = ($current_page - 1) * $rows_per_page;

// Final Solr Query URL with pagination
$request_url = $solr_url . '&q=' . urlencode($query) . '&start=' . $start . '&rows=' . $rows_per_page;

// Fetch data from Solr
$response = file_get_contents($request_url);
$data = json_decode($response, true);

$results = $data['response']['docs'] ?? [];
$total_results = $data['response']['numFound'] ?? 0;

// Calculate pagination details
$total_pages = ceil($total_results / $rows_per_page);

function render_pagination($current_page, $total_pages, $query_params) {
    if ($total_pages <= 1) return ''; // No need for pagination if there's only one page

    $pagination_html = '<nav aria-label="Pagination"><ul class="pagination justify-content-center">';

    // Add "First" button
    if ($current_page > 1) {
        $pagination_html .= '<li class="page-item"><a class="page-link" href="?' . http_build_query(array_merge($query_params, ['page' => 1])) . '">&laquo; First</a></li>';
    }

    // Add "Previous" button
    if ($current_page > 1) {
        $pagination_html .= '<li class="page-item"><a class="page-link" href="?' . http_build_query(array_merge($query_params, ['page' => $current_page - 1])) . '">&lsaquo; Prev</a></li>';
    }

    // Determine pagination range
    $range = 2; // Show 2 pages before and after the current page
    $start = max(1, $current_page - $range);
    $end = min($total_pages, $current_page + $range);

    // Add leading dots if needed
    if ($start > 2) {
        $pagination_html .= '<li class="page-item"><a class="page-link" href="?' . http_build_query(array_merge($query_params, ['page' => 1])) . '">1</a></li>';
        $pagination_html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
    }

    // Add page buttons within the calculated range
    for ($i = $start; $i <= $end; $i++) {
        if ($i == $current_page) {
            // Highlight current page
            $pagination_html .= '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
        } else {
            $pagination_html .= '<li class="page-item"><a class="page-link" href="?' . http_build_query(array_merge($query_params, ['page' => $i])) . '">' . $i . '</a></li>';
        }
    }

    // Add trailing dots if needed
    if ($end < $total_pages - 1) {
        $pagination_html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        $pagination_html .= '<li class="page-item"><a class="page-link" href="?' . http_build_query(array_merge($query_params, ['page' => $total_pages])) . '">' . $total_pages . '</a></li>';
    }

    // Add "Next" button
    if ($current_page < $total_pages) {
        $pagination_html .= '<li class="page-item"><a class="page-link" href="?' . http_build_query(array_merge($query_params, ['page' => $current_page + 1])) . '">Next &rsaquo;</a></li>';
    }

    // Add "Last" button
    if ($current_page < $total_pages) {
        $pagination_html .= '<li class="page-item"><a class="page-link" href="?' . http_build_query(array_merge($query_params, ['page' => $total_pages])) . '">&raquo; Last</a></li>';
    }

    $pagination_html .= '</ul></nav>';
    return $pagination_html;
}

// Pagination HTML (call this function in both places: above and below results)
$pagination = render_pagination($current_page, $total_pages, $_GET);


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-SgOJa3DmI69IUzQ2PVdRZhwQ+dy64/BUtbMJw1MZ8t5HZApcHrRKUc4W0kG879m7" crossorigin="anonymous">
    <link rel="icon" type="image/x-icon" href="img/favicon.ico?_=9.8.1">
    <link rel="shortcut icon" type="image/x-icon" href="img/favicon.ico?_=9.8.1">
    <title>ERN3 Source Image Search</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }

        form {
            margin-bottom: 20px;
        }

        .result {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 1px solid #ccc;
            padding-bottom: 10px;
        }

        .result img {
            width: 250px;
            height: 250px;
            object-fit: cover;
            margin-right: 15px;
        }

        .details {
            flex: 1;
        }

        .details h3 {
            margin-top: 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        table, th, td {
            border: 1px solid #ddd;
        }

        th, td {
            padding: 8px;
            text-align: left;
        }

        th {
            background-color: #f4f4f4;
        }

        .download-link {
            margin-top: 10px;
            display: inline-block;
        }
    </style>
</head>
<body>
<h1>ERN3 Source Image Search</h1>

<!-- Query Builder Form -->
<form method="GET" id="search-form">
    <label for="artist_name">Artist Name:</label>
    <input type="text" id="artist_name" name="artist_name" value="<?= htmlspecialchars($_GET['artist_name'] ?? '') ?>"
           placeholder="e.g., Sin4Hood"><br><br>

    <label for="genre">Genre:</label>
    <input type="text" id="genre" name="genre" value="<?= htmlspecialchars($_GET['genre'] ?? '') ?>"
           placeholder="e.g., Instrumental"><br><br>

    <label for="title">Title:</label>
    <input type="text" id="title" name="title" value="<?= htmlspecialchars($_GET['title'] ?? '') ?>"
           placeholder="e.g., Jolo"><br><br>

    <label for="label_name">Label Name:</label>
    <input type="text" id="label_name" name="label_name" value="<?= htmlspecialchars($_GET['label_name'] ?? '') ?>"
           placeholder="e.g., 4Hood Music Group"><br><br>

    <label>
        <input type="checkbox" name="include_no_images" <?= isset($_GET['include_no_images']) ? 'checked' : '' ?>>
        Include results without images
    </label><br><br>

    <button type="submit" class="btn btn-primary">Search</button>
    <button type="button" class="btn btn-secondary" id="clear-button">Clear</button>
</form>

<script>
    // JavaScript to handle the "Clear" button functionality
    document.getElementById('clear-button').addEventListener('click', function () {
        // Select the form and reset all inputs
        const form = document.getElementById('search-form');
        form.reset();
        // Optionally clear the URL parameters as well
        window.location.href = window.location.pathname;
    });
</script>

<?= $pagination ?>

<h2>Results:</h2>

<!-- Display Results -->
<?php if (count($results) > 0): ?>
    <?php foreach ($results as $result): ?>
        <div class="result" style="<?= empty($result['image_url']) ? 'opacity: 0.8; background-color: #f9f9f9;' : '' ?>">
            <?php if (!empty($result['image_url'])): ?>
                <!-- If image_url exists, display the image -->
                <a href="<?= htmlspecialchars($result['image_url']) ?>" download>
                    <img src="<?= htmlspecialchars($result['image_url']) ?>"
                         alt="<?= htmlspecialchars($result['title'] ?? 'No Image') ?>">
                </a>
            <?php else: ?>
                <!-- If no image_url exists, show placeholder -->
                <div style="width: 215px; height: 215px; background-color: #ccc; display: flex; justify-content: center; align-items: center; color: #666; margin-right: 15px;">
                    No Image
                </div>
            <?php endif; ?>

            <div class="details">
                <h3 style="<?= empty($result['image_url']) ? 'color: #888;' : '' ?>">
                    <?= htmlspecialchars($result['title'] ?? 'No Title') ?>
                </h3>

                <!-- Explicitly List All Fields -->
                <div><strong>SRC ID:</strong> <?= htmlspecialchars($result['src_id'] ?? 'N/A') ?></div>
                <div><strong>Title:</strong> <?= htmlspecialchars($result['title'] ?? 'N/A') ?></div>
                <div><strong>Artist Name:</strong> <?= htmlspecialchars($result['artist_name'] ?? 'N/A') ?></div>
                <div><strong>ISRC:</strong> <?= htmlspecialchars($result['isrc'] ?? 'N/A') ?></div>
                <div><strong>Genre:</strong> <?= htmlspecialchars($result['genre'] ?? 'N/A') ?></div>
                <div><strong>Sub-Genre:</strong> <?= htmlspecialchars($result['sub_genre'] ?? 'N/A') ?></div>
                <div><strong>Label Name:</strong> <?= htmlspecialchars($result['label_name'] ?? 'N/A') ?></div>
                <div><strong>Timestamp:</strong> <?= htmlspecialchars($result['timestamp'] ?? 'N/A') ?></div>

                <?php if (!empty($result['image_url'])): ?>
                    <p><strong>Image URL:</strong>
                        <a href="<?= htmlspecialchars($result['image_url']) ?>" download>
                            <?= htmlspecialchars($result['image_url']) ?>
                        </a>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
<?php else: ?>
    <p>No results found for the given query.</p>
<?php endif; ?>

<?= $pagination ?>

<div class="mt-4">
    <button class="btn btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#debug-section"
            aria-expanded="false" aria-controls="debug-section">
        Toggle Debug Information
    </button>
    <div class="collapse mt-3" id="debug-section">
        <pre style="background-color: #f8f9fa; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
<?php
var_dump($request_url);
var_dump($results);
?>
        </pre>
    </div>
</div>

</body>
<footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-k6d4wzSIapyDyv1kpU366/PK5hCdSbCRGRCMv+eplOQJWyd1fbcAu9OCUj5zNLiq"
            crossorigin="anonymous"></script>
</footer>
</html>
