<?php
require_once 'includes/db_connect.php';  // adjust path as needed
require_once 'includes/header.php';      // adjust path as needed
?>
<div class="container mt-5">
    <h2 class="mb-4">
        <i class="fas fa-clipboard-list"></i> Product Audit Logs
    </h2>
    <?php
    echo $_SESSION['message'] ?? '';
    unset($_SESSION['message']);
    ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <button class="btn btn-primary" id="refreshLogs">
            <i class="fas fa-sync-alt"></i> Refresh
        </button>
        <input type="text" id="logSearch" class="form-control"
            placeholder="Search logs..." style="width: 250px;">
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
                    $stmt = $pdo->query('SELECT * FROM product_audit ORDER BY changed_at DESC');
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $id         = htmlspecialchars($row['id']);
                        $product_id = htmlspecialchars($row['product_id']);
                        $action     = htmlspecialchars(ucfirst($row['action']));
                        $changed_by = htmlspecialchars($row['changed_by']);
                        $changed_at = htmlspecialchars($row['changed_at']);

                        // Original raw strings from DB
                        $old_raw = $row['old_values'] ?? '';
                        $new_raw = $row['new_values'] ?? '';

                        // Convert them safely to JS strings
                        $old_js = json_encode($old_raw);
                        $new_js = json_encode($new_raw);

                        echo "<tr>
                            <td class='text-center'>{$id}</td>
                            <td class='text-center'>{$product_id}</td>
                            <td class='text-center'>{$action}</td>
                            <td class='text-center'>{$changed_by}</td>
                            <td class='text-center'>{$changed_at}</td>
                            <td class='text-center'>
                                <button class='btn btn-sm btn-info view-old'
                                        data-old='{$old_js}'>
                                    <i class='fas fa-eye'></i>
                                </button>
                            </td>
                            <td class='text-center'>
                                <button class='btn btn-sm btn-success view-new'
                                        data-new='{$new_js}'>
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
<div class="modal fade" id="oldValuesModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content shadow">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title">
                    <i class="fas fa-eye"></i> Old Values (Collapsible List)
                </h5>
                <button type="button" class="btn-close text-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <div class="modal-body" id="oldValuesContent"
                style="max-height: 450px; overflow: auto;"></div>
        </div>
    </div>
</div>

<!-- New Values Modal -->
<div class="modal fade" id="newValuesModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content shadow">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">
                    <i class="fas fa-eye"></i> New Values (Collapsible List)
                </h5>
                <button type="button" class="btn-close text-white" data-bs-dismiss="modal"
                    aria-label="Close"></button>
            </div>
            <div class="modal-body" id="newValuesContent"
                style="max-height: 450px; overflow: auto;"></div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<link href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>

<script>
    $(document).ready(function() {
        var table = $('#auditTable').DataTable({
            paging: true,
            pageLength: 10,
            lengthChange: false,
            ordering: true,
            order: [
                [4, "desc"]
            ],
            info: true,
            autoWidth: false,
            searching: true,
            columnDefs: [{
                    orderable: false,
                    targets: [5, 6]
                },
                {
                    className: 'align-middle',
                    targets: '_all'
                }
            ]
        });

        $('#refreshLogs').click(function() {
            location.reload();
        });

        $('#logSearch').on('keyup', function() {
            table.search(this.value).draw();
        });

        // Escapes HTML in strings to prevent any injection
        function escapeHTML(str) {
            return str
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;');
        }

        // Build collapsible tree-like HTML
        // Each object or array node can be expanded/collapsed
        var nodeCounter = 0; // unique IDs for toggles

        function renderCollapsibleList(obj) {
            if (obj === null) {
                return '<em class="text-muted">null</em>';
            }
            if (typeof obj !== 'object') {
                return `<span>${escapeHTML(String(obj))}</span>`;
            }
            if (Array.isArray(obj)) {
                if (obj.length === 0) {
                    return '<em class="text-muted">[] (empty array)</em>';
                }
                let html = '<ul class="list-unstyled ms-3">';
                obj.forEach((item, idx) => {
                    html += `<li><strong>[${idx}]</strong>: ${renderCollapsibleList(item)}</li>`;
                });
                html += '</ul>';
                return html;
            } else {
                var keys = Object.keys(obj);
                if (!keys.length) {
                    return '<em class="text-muted">{ } (empty object)</em>';
                }
                let html = '<ul class="list-unstyled ms-3">';
                keys.forEach(key => {
                    let value = obj[key];
                    // We'll detect if value is object or array => collapsible
                    if (value && typeof value === 'object') {
                        nodeCounter++;
                        let nodeId = 'node_' + nodeCounter;
                        // Build child HTML
                        let childHTML = renderCollapsibleList(value);
                        // We'll wrap child in a <div> toggled by expand/collapse
                        html += `
                      <li>
                        <strong>${escapeHTML(key)}</strong>: 
                        <button type="button" 
                                class="btn btn-sm btn-outline-secondary toggle-btn" 
                                data-target="#${nodeId}" 
                                style="margin-left:0.5em;">Toggle</button>
                        <div id="${nodeId}" class="mt-2">
                          ${childHTML}
                        </div>
                      </li>
                    `;
                    } else {
                        // Plain value
                        html += `
                      <li>
                        <strong>${escapeHTML(key)}</strong>: 
                        <span>${escapeHTML(String(value))}</span>
                      </li>
                    `;
                    }
                });
                html += '</ul>';
                return html;
            }
        }

        // Attempt to parse JSON, or fallback to raw text
        function parseJSONFriendly(jsonString) {
            if (!jsonString) {
                return '<em class="text-muted">(No data)</em>';
            }
            try {
                let parsed = JSON.parse(jsonString);
                nodeCounter = 0;
                return renderCollapsibleList(parsed);
            } catch (err) {
                // Fallback: raw text with <pre>
                return `<pre class="bg-light p-2 rounded">${escapeHTML(jsonString)}</pre>`;
            }
        }

        // Expand/Collapse logic
        $(document).on('click', '.toggle-btn', function(e) {
            e.preventDefault();
            let target = $(this).data('target');
            $(target).toggle();
        });

        $('#auditTable').on('click', '.view-old', function() {
            let oldValues = $(this).data('old') || '';
            let friendly = parseJSONFriendly(oldValues);
            $('#oldValuesContent').html(friendly);
            let modal = new bootstrap.Modal(document.getElementById('oldValuesModal'));
            modal.show();
        });

        $('#auditTable').on('click', '.view-new', function() {
            let newValues = $(this).data('new') || '';
            let friendly = parseJSONFriendly(newValues);
            $('#newValuesContent').html(friendly);
            let modal = new bootstrap.Modal(document.getElementById('newValuesModal'));
            modal.show();
        });
    });
</script>

<?php require_once 'includes/footer.php'; ?>