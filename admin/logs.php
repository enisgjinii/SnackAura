<?php
require_once 'includes/db_connect.php';  // Adjust path as needed
require_once 'includes/header.php';      // Adjust path as needed

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
<div class="container mt-5">
    <h2 class="mb-4">
        <i class="fas fa-clipboard-list"></i> Product Audit Logs
    </h2>
    <?php
    // Display session message if exists
    if (isset($_SESSION['message'])) {
        echo '<div class="alert alert-info">' . htmlspecialchars($_SESSION['message']) . '</div>';
        unset($_SESSION['message']);
    }
    ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <button class="btn btn-primary" id="refreshLogs">
            <i class="fas fa-sync-alt"></i> Refresh
        </button>
        <input type="text" id="logSearch" class="form-control" placeholder="Search logs..." style="width: 250px;">
    </div>
    <div class="table-responsive shadow-sm rounded">
        <table id="auditTable" class="table table-striped table-bordered align-middle">
            <thead class="table-dark">
                <tr>
                    <th class="text-center">ID</th>
                    <th class="text-center">Product ID</th>
                    <th class="text-center">Action</th>
                    <th class="text-center">Changed By</th>
                    <th class="text-center">Changed At</th>
                    <th class="text-center">Old Values</th>
                    <th class="text-center">New Values</th>
                </tr>
            </thead>
            <tbody>
                <?php
                try {
                    // Fetch audit logs ordered by most recent changes
                    $stmt = $pdo->query('SELECT * FROM product_audit ORDER BY changed_at DESC');
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        // Escape output to prevent XSS
                        $id         = htmlspecialchars($row['id']);
                        $product_id = htmlspecialchars($row['product_id']);
                        $action     = htmlspecialchars(ucfirst($row['action']));
                        $changed_by = htmlspecialchars($row['changed_by']);
                        $changed_at = htmlspecialchars($row['changed_at']);

                        // Original raw JSON strings from DB
                        $old_raw = $row['old_values'] ?? '';
                        $new_raw = $row['new_values'] ?? '';

                        // Safely encode JSON for embedding in data attributes
                        $old_js = htmlspecialchars(json_encode($old_raw), ENT_QUOTES, 'UTF-8');
                        $new_js = htmlspecialchars(json_encode($new_raw), ENT_QUOTES, 'UTF-8');

                        echo "<tr>
                            <td class='text-center'>{$id}</td>
                            <td class='text-center'>{$product_id}</td>
                            <td class='text-center'>{$action}</td>
                            <td class='text-center'>{$changed_by}</td>
                            <td class='text-center'>{$changed_at}</td>
                            <td class='text-center'>
                                <button class='btn btn-sm btn-info view-old' data-old='{$old_js}'>
                                    <i class='fas fa-eye'></i>
                                </button>
                            </td>
                            <td class='text-center'>
                                <button class='btn btn-sm btn-success view-new' data-new='{$new_js}'>
                                    <i class='fas fa-eye'></i>
                                </button>
                            </td>
                          </tr>";
                    }
                } catch (PDOException $e) {
                    echo '<tr>
                        <td colspan="7" class="text-danger fw-bold text-center">
                            Error fetching logs: ' . htmlspecialchars($e->getMessage()) . '
                        </td>
                      </tr>';
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Old Values Modal -->
<div class="modal fade" id="oldValuesModal" tabindex="-1" aria-labelledby="oldValuesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl"> <!-- Increased size for better visibility -->
        <div class="modal-content shadow">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="oldValuesModalLabel">
                    <i class="fas fa-eye"></i> Old Values
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="oldValuesContent" style="max-height: 70vh; overflow: auto;"></div>
        </div>
    </div>
</div>

<!-- New Values Modal -->
<div class="modal fade" id="newValuesModal" tabindex="-1" aria-labelledby="newValuesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl"> <!-- Increased size for better visibility -->
        <div class="modal-content shadow">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="newValuesModalLabel">
                    <i class="fas fa-eye"></i> New Values
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="newValuesContent" style="max-height: 70vh; overflow: auto;"></div>
        </div>
    </div>
</div>

<!-- Include necessary scripts and styles -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Bootstrap Bundle includes Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- DataTables CSS and JS -->
<link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
<!-- Font Awesome for icons -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

<!-- Custom Styles for Highlighting -->
<style>
    /* Highlight changed fields in Old Values (red) */
    .highlight-old {
        background-color: #f8d7da;
        /* Bootstrap danger background */
        border-radius: 4px;
        padding: 2px 4px;
    }

    /* Highlight changed fields in New Values (green) */
    .highlight-new {
        background-color: #d4edda;
        /* Bootstrap success background */
        border-radius: 4px;
        padding: 2px 4px;
    }

    /* Cursor pointer for toggle buttons */
    .toggle-btn {
        cursor: pointer;
    }

    /* Additional styling for nested tables */
    .nested-table {
        margin-left: 20px;
        margin-top: 10px;
    }

    /* Styling for JSON keys */
    .json-key {
        font-weight: bold;
    }

    /* Styling for JSON values */
    .json-value {
        margin-left: 5px;
    }

    /* Styling for list items */
    .json-list {
        list-style-type: none;
        padding-left: 0;
    }
</style>

<script>
    $(document).ready(function() {
        // Initialize DataTable with desired configurations
        var table = $('#auditTable').DataTable({
            paging: true,
            pageLength: 10,
            lengthChange: false,
            ordering: true,
            order: [
                [4, "desc"] // Order by 'Changed At' descending
            ],
            info: true,
            autoWidth: false,
            searching: true,
            columnDefs: [{
                    orderable: false,
                    targets: [5, 6] // Disable ordering on 'Old Values' and 'New Values' columns
                },
                {
                    className: 'align-middle',
                    targets: '_all'
                }
            ]
        });

        // Refresh Logs button functionality
        $('#refreshLogs').click(function() {
            location.reload();
        });

        // Search functionality
        $('#logSearch').on('keyup', function() {
            table.search(this.value).draw();
        });

        // Utility function to escape HTML to prevent XSS
        function escapeHTML(str) {
            if (typeof str !== 'string') {
                return str;
            }
            return str
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        // Function to find differences between two objects
        function findDifferences(oldObj, newObj, path = '') {
            let diffs = new Set();

            // If both are non-objects, direct comparison
            if (typeof oldObj !== 'object' || oldObj === null ||
                typeof newObj !== 'object' || newObj === null) {
                if (oldObj !== newObj) {
                    diffs.add(path || '(root)');
                }
                return diffs;
            }

            // Handle arrays
            if (Array.isArray(oldObj) && Array.isArray(newObj)) {
                let maxLength = Math.max(oldObj.length, newObj.length);
                for (let i = 0; i < maxLength; i++) {
                    let newPath = path + `[${i}]`;
                    let childDiffs = findDifferences(oldObj[i], newObj[i], newPath);
                    for (let d of childDiffs) {
                        diffs.add(d);
                    }
                }
                return diffs;
            } else if (Array.isArray(oldObj) !== Array.isArray(newObj)) {
                // One is array, the other is not
                diffs.add(path || '(root)');
                return diffs;
            }

            // Both are objects
            const oldKeys = Object.keys(oldObj);
            const newKeys = Object.keys(newObj);
            const allKeys = new Set([...oldKeys, ...newKeys]);

            for (let key of allKeys) {
                let newPath = path ? path + '.' + key : key;
                if (!(key in oldObj)) {
                    // Key missing in old
                    diffs.add(newPath);
                } else if (!(key in newObj)) {
                    // Key missing in new
                    diffs.add(newPath);
                } else {
                    // Compare deeper
                    let childDiffs = findDifferences(oldObj[key], newObj[key], newPath);
                    for (let d of childDiffs) {
                        diffs.add(d);
                    }
                }
            }
            return diffs;
        }

        // Function to render data in a structured and readable format
        var nodeCounter = 0; // Unique IDs for toggle buttons

        function renderStructuredData(obj, diffSet, highlightType, currentPath = '') {
            // Base cases for non-objects
            if (obj === null) {
                let highlightClass = diffSet.has(currentPath) ? (highlightType === 'old' ? 'highlight-old' : 'highlight-new') : '';
                return `<span class="${highlightClass}"><em>null</em></span>`;
            }
            if (typeof obj !== 'object') {
                let highlightClass = diffSet.has(currentPath) ? (highlightType === 'old' ? 'highlight-old' : 'highlight-new') : '';
                return `<span class="${highlightClass}">${escapeHTML(String(obj))}</span>`;
            }

            // Handle arrays
            if (Array.isArray(obj)) {
                if (obj.length === 0) {
                    return '<em class="text-muted">[] (empty array)</em>';
                }
                let html = '<ul class="json-list">';
                obj.forEach((item, idx) => {
                    let childPath = currentPath + `[${idx}]`;
                    html += `<li>
                                <span class="json-key">[${idx}]:</span>
                                ${renderStructuredData(item, diffSet, highlightType, childPath)}
                             </li>`;
                });
                html += '</ul>';
                return html;
            }

            // Handle objects
            const keys = Object.keys(obj);
            if (keys.length === 0) {
                return '<em class="text-muted">{ } (empty object)</em>';
            }

            let html = '<table class="table table-sm table-bordered nested-table">';
            html += '<tbody>';
            keys.forEach(key => {
                let value = obj[key];
                let childPath = currentPath ? `${currentPath}.${key}` : key;

                // Determine if this key has differences
                let isDiff = diffSet.has(childPath);

                // Determine highlighting
                let highlightClass = isDiff ? (highlightType === 'old' ? 'highlight-old' : 'highlight-new') : '';

                if (typeof value === 'object' && value !== null) {
                    nodeCounter++;
                    let nodeId = 'node_' + nodeCounter;
                    let childHTML = renderStructuredData(value, diffSet, highlightType, childPath);

                    html += `
                      <tr>
                        <td class="json-key ${highlightClass}" style="width: 200px;">
                            ${escapeHTML(key)}
                        </td>
                        <td>
                            <button type="button" 
                                    class="btn btn-sm btn-outline-secondary toggle-btn" 
                                    data-target="#${nodeId}" 
                                    style="margin-bottom: 5px;">
                                <i class="fas fa-plus"></i> Toggle
                            </button>
                            <div id="${nodeId}" style="display: none;">
                                ${childHTML}
                            </div>
                        </td>
                      </tr>
                    `;
                } else {
                    // Leaf node
                    let valueDisplay = (value === null) ? '<em>null</em>' : escapeHTML(String(value));
                    let valueClass = isDiff ? (highlightType === 'old' ? 'highlight-old' : 'highlight-new') : '';
                    html += `
                      <tr>
                        <td class="json-key ${highlightClass}" style="width: 200px;">
                            ${escapeHTML(key)}
                        </td>
                        <td class="json-value ${valueClass}">
                            ${valueDisplay}
                        </td>
                      </tr>
                    `;
                }
            });
            html += '</tbody></table>';
            return html;
        }

        // Function to parse JSON and render with differences highlighted
        function parseAndRenderStructured(oldJson, newJson, highlightType) {
            if (!oldJson && highlightType === 'old') {
                return '<em class="text-muted">(No old data)</em>';
            } else if (!newJson && highlightType === 'new') {
                return '<em class="text-muted">(No new data)</em>';
            }

            let oldObj, newObj;
            try {
                oldObj = JSON.parse(oldJson);
            } catch {
                oldObj = oldJson;
            }
            try {
                newObj = JSON.parse(newJson);
            } catch {
                newObj = newJson;
            }

            // Find differences between old and new
            let diffSet = findDifferences(oldObj, newObj);

            // Render based on highlight type
            let html;
            if (highlightType === 'old') {
                html = renderStructuredData(oldObj, diffSet, 'old');
            } else if (highlightType === 'new') {
                html = renderStructuredData(newObj, diffSet, 'new');
            } else {
                html = '<em class="text-muted">No data to display.</em>';
            }

            return html;
        }

        // Toggle button functionality
        $(document).on('click', '.toggle-btn', function(e) {
            e.preventDefault();
            let target = $(this).data('target');
            let isVisible = $(target).is(':visible');
            $(target).toggle();

            // Change icon based on toggle state
            let icon = isVisible ? 'fa-plus' : 'fa-minus';
            $(this).find('i').removeClass('fa-plus fa-minus').addClass(icon);
        });

        // Event handler for viewing Old Values
        $('#auditTable').on('click', '.view-old', function() {
            let oldValues = $(this).data('old') || '';
            // Fetch corresponding new values from the same row
            let row = $(this).closest('tr');
            let newValues = row.find('.view-new').data('new') || '';

            let content = parseAndRenderStructured(oldValues, newValues, 'old');
            $('#oldValuesContent').html(content);

            let modal = new bootstrap.Modal(document.getElementById('oldValuesModal'));
            modal.show();
        });

        // Event handler for viewing New Values
        $('#auditTable').on('click', '.view-new', function() {
            let newValues = $(this).data('new') || '';
            // Fetch corresponding old values from the same row
            let row = $(this).closest('tr');
            let oldValues = row.find('.view-old').data('old') || '';

            let content = parseAndRenderStructured(oldValues, newValues, 'new');
            $('#newValuesContent').html(content);

            let modal = new bootstrap.Modal(document.getElementById('newValuesModal'));
            modal.show();
        });
    });
</script>

<?php require_once 'includes/footer.php'; ?>