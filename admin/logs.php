<?php
ob_start();
session_start();
require_once 'includes/db_connect.php';
require_once 'includes/header.php';

// Function to sanitize output
function s($data)
{
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}
?>
<div class="container mt-5">
    <h2 class="mb-4"><i class="fas fa-clipboard-list"></i> Product Audit Logs</h2>

    <!-- Toast Notifications -->
    <?php if (isset($_SESSION['toast'])): ?>
        <div class="position-fixed bottom-0 end-0 p-3" style="z-index:1100;">
            <div class="toast align-items-center text-white bg-<?= s($_SESSION['toast']['type']) ?> border-0" role="alert">
                <div class="d-flex">
                    <div class="toast-body"><?= s($_SESSION['toast']['message']) ?></div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            </div>
        </div>
        <?php unset($_SESSION['toast']); ?>
    <?php endif; ?>

    <!-- Filters Section -->
    <div class="card mb-4">
        <div class="card-body">
            <form id="filterForm" class="row g-3">
                <div class="col-md-3">
                    <label for="filterAction" class="form-label">Action Type</label>
                    <select id="filterAction" class="form-select">
                        <option value="">All Actions</option>
                        <option value="Create" <?= (isset($_GET['action']) && $_GET['action'] === 'Create') ? 'selected' : '' ?>>Create</option>
                        <option value="Update" <?= (isset($_GET['action']) && $_GET['action'] === 'Update') ? 'selected' : '' ?>>Update</option>
                        <option value="Delete" <?= (isset($_GET['action']) && $_GET['action'] === 'Delete') ? 'selected' : '' ?>>Delete</option>
                        <!-- Add more action types as needed -->
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="filterUser" class="form-label">Changed By</label>
                    <input type="text" id="filterUser" class="form-control" placeholder="Username" value="<?= isset($_GET['changed_by']) ? s($_GET['changed_by']) : '' ?>">
                </div>
                <div class="col-md-3">
                    <label for="filterDateFrom" class="form-label">Date From</label>
                    <input type="date" id="filterDateFrom" class="form-control" value="<?= isset($_GET['date_from']) ? s($_GET['date_from']) : '' ?>">
                </div>
                <div class="col-md-3">
                    <label for="filterDateTo" class="form-label">Date To</label>
                    <input type="date" id="filterDateTo" class="form-control" value="<?= isset($_GET['date_to']) ? s($_GET['date_to']) : '' ?>">
                </div>
                <div class="col-12">
                    <button type="button" id="applyFilters" class="btn btn-primary">Apply Filters</button>
                    <button type="button" id="resetFilters" class="btn btn-secondary">Reset</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Audit Logs Table -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <button class="btn btn-primary btn-sm" id="refreshLogs"><i class="fas fa-sync-alt"></i> Refresh</button>
        <input type="text" id="logSearch" class="form-control form-control-sm" placeholder="Search logs..." style="width:250px;">
    </div>
    <div class="table-responsive shadow-sm rounded">
        <table id="auditTable" class="table table-striped table-bordered align-middle small">
            <thead class="table-dark">
                <tr>
                    <th class="text-center">ID</th>
                    <th class="text-center">Product ID</th>
                    <th class="text-center">Action</th>
                    <th class="text-center">Changed By</th>
                    <th class="text-center">Changed At</th>
                    <th class="text-center">Changes</th>
                </tr>
            </thead>
            <tbody>
                <?php
                try {
                    // Prepared statement for enhanced security and filtering
                    $query = 'SELECT * FROM product_audit WHERE 1';

                    // Applying filters if any
                    $params = [];
                    if (isset($_GET['action']) && $_GET['action'] !== '') {
                        $query .= ' AND action = :action';
                        $params[':action'] = $_GET['action'];
                    }
                    if (isset($_GET['changed_by']) && $_GET['changed_by'] !== '') {
                        $query .= ' AND changed_by LIKE :changed_by';
                        $params[':changed_by'] = '%' . $_GET['changed_by'] . '%';
                    }
                    if (isset($_GET['date_from']) && $_GET['date_from'] !== '') {
                        $query .= ' AND changed_at >= :date_from';
                        $params[':date_from'] = $_GET['date_from'] . ' 00:00:00';
                    }
                    if (isset($_GET['date_to']) && $_GET['date_to'] !== '') {
                        $query .= ' AND changed_at <= :date_to';
                        $params[':date_to'] = $_GET['date_to'] . ' 23:59:59';
                    }

                    $query .= ' ORDER BY changed_at DESC';
                    $stmt = $pdo->prepare($query);
                    $stmt->execute($params);

                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $id = s($row['id']);
                        $pid = s($row['product_id']);
                        $action = s(ucfirst($row['action']));
                        $by = s($row['changed_by']);
                        $at = s($row['changed_at']);
                        $old = s($row['old_values'] ?? '');
                        $new = s($row['new_values'] ?? '');

                        // Highlight row based on action type
                        $rowClass = '';
                        switch (strtolower($row['action'])) {
                            case 'create':
                                $rowClass = 'table-success';
                                break;
                            case 'update':
                                $rowClass = 'table-warning';
                                break;
                            case 'delete':
                                $rowClass = 'table-danger';
                                break;
                            default:
                                $rowClass = '';
                        }

                        echo "<tr class='{$rowClass}'>
                                <td class='text-center'>{$id}</td>
                                <td class='text-center'>{$pid}</td>
                                <td class='text-center'>{$action}</td>
                                <td class='text-center'>{$by}</td>
                                <td class='text-center'>{$at}</td>
                                <td class='text-center'>
                                    <button class='btn btn-sm btn-warning view-changes' data-old='{$old}' data-new='{$new}'>
                                        <i class='fas fa-exchange-alt'></i> View
                                    </button>
                                </td>
                              </tr>";
                    }
                } catch (PDOException $e) {
                    echo "<tr><td colspan='6' class='text-danger fw-bold text-center'>Error: " . s($e->getMessage()) . "</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Changes Modal -->
<div class="modal fade" id="changesModal" tabindex="-1" aria-labelledby="changesModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen">
        <div class="modal-content shadow">
            <div class="modal-header bg-warning text-white">
                <h5 class="modal-title" id="changesModalLabel"><i class="fas fa-exchange-alt"></i> Changes</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="changesContent" style="max-height:70vh; overflow:auto;">
                <!-- Changes will be dynamically injected here -->
            </div>
            <!-- Change Summary -->
            <div class="modal-footer">
                <span id="changesSummary" class="text-muted"></span>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Include JavaScript and CSS Dependencies -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<link href="https://cdn.datatables.net/buttons/2.3.6/css/buttons.bootstrap5.min.css" rel="stylesheet">
<script src="https://cdn.datatables.net/buttons/2.3.6/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.bootstrap5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.print.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.3.6/js/buttons.colVis.min.js"></script>
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Custom Styles -->
<style>
    .highlight-old {
        background-color: #f8d7da;
        border-radius: 4px;
        padding: 2px 4px;
    }

    .highlight-new {
        background-color: #d4edda;
        border-radius: 4px;
        padding: 2px 4px;
    }

    .nested-table {
        margin-left: 20px;
        margin-top: 10px;
    }

    .json-key {
        font-weight: bold;
    }

    .json-value {
        margin-left: 5px;
    }

    .json-list {
        list-style-type: none;
        padding-left: 0;
    }

    .toggle-btn {
        cursor: pointer;
        margin-bottom: 5px;
    }

    .toast-container .toast {
        min-width: 250px;
    }

    /* Summary Enhancement */
    #changesSummary {
        font-size: 0.9rem;
    }

    /* Filter Form Enhancements */
    #filterForm .form-label {
        font-weight: 500;
    }

    /* Modal Grid Layout */
    .changes-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
    }

    .change-item {
        padding: 10px;
        border: 1px solid #dee2e6;
        border-radius: 5px;
        background-color: #f9f9f9;
    }

    .change-item .field-name {
        font-weight: bold;
        margin-bottom: 5px;
    }

    .change-item .field-value {
        word-wrap: break-word;
    }

    .highlight-diff {
        padding: 2px 4px;
        border-radius: 3px;
    }

    .added {
        background-color: #d4edda;
    }

    .removed {
        background-color: #f8d7da;
    }

    .modified {
        background-color: #fff3cd;
    }
</style>

<!-- Custom Scripts -->
<script>
    $(function() {
        // Initialize DataTable
        var table = $('#auditTable').DataTable({
            paging: true,
            pageLength: 10,
            lengthChange: false,
            ordering: true,
            order: [
                [4, 'desc']
            ],
            info: true,
            searching: true,
            autoWidth: false,
            dom: '<"row mb-3"<"col-12 d-flex justify-content-between align-items-center"lBf>>rt<"row mt-3"<"col-sm-12 col-md-6 d-flex justify-content-start"i><"col-sm-12 col-md-6 d-flex justify-content-end"p>>',
            buttons: [{
                    extend: 'csv',
                    text: '<i class="fas fa-file-csv"></i> CSV',
                    className: 'btn btn-primary btn-sm'
                },
                {
                    extend: 'pdf',
                    text: '<i class="fas fa-file-pdf"></i> PDF',
                    className: 'btn btn-primary btn-sm'
                },
                {
                    extend: 'colvis',
                    text: '<i class="fas fa-columns"></i> Columns',
                    className: 'btn btn-primary btn-sm'
                },
                {
                    extend: 'copy',
                    text: '<i class="fas fa-copy"></i> Copy',
                    className: 'btn btn-primary btn-sm'
                }
            ],
            initComplete: function() {
                this.api().buttons().container().addClass('d-flex flex-wrap gap-2');
            },
            columnDefs: [{
                orderable: false,
                targets: [5]
            }, {
                className: 'align-middle',
                targets: '_all'
            }],
            language: {
                url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/de-DE.json'
            }
        });

        // Custom Search Functionality
        $('#logSearch').on('keyup', function() {
            table.search(this.value).draw();
        });

        // Refresh Logs Button
        $('#refreshLogs').click(function() {
            location.reload();
        });

        // Filter Functionality
        $('#applyFilters').click(function() {
            var action = $('#filterAction').val();
            var changed_by = $('#filterUser').val();
            var date_from = $('#filterDateFrom').val();
            var date_to = $('#filterDateTo').val();

            // Reload table with new filters via AJAX or by manipulating DataTable
            // For simplicity, we'll reload the page with query parameters
            var query = [];
            if (action) query.push('action=' + encodeURIComponent(action));
            if (changed_by) query.push('changed_by=' + encodeURIComponent(changed_by));
            if (date_from) query.push('date_from=' + encodeURIComponent(date_from));
            if (date_to) query.push('date_to=' + encodeURIComponent(date_to));

            var queryString = query.length > 0 ? '?' + query.join('&') : '';
            window.location.href = window.location.pathname + queryString;
        });

        // Reset Filters
        $('#resetFilters').click(function() {
            $('#filterForm')[0].reset();
            window.location.href = window.location.pathname;
        });

        // Escape HTML to prevent XSS
        function eH(str) {
            if (typeof str !== 'string') return str;
            return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        }

        // Function to find differences between two objects
        function diffs(o, n, p = '') {
            let d = new Set();
            if (typeof o !== 'object' || o === null || typeof n !== 'object' || n === null) {
                if (o !== n) d.add(p || '(root)');
                return d;
            }
            if (Array.isArray(o) && Array.isArray(n)) {
                let m = Math.max(o.length, n.length);
                for (let i = 0; i < m; i++) {
                    let c = diffs(o[i], n[i], p + '[' + i + ']');
                    for (let x of c) d.add(x);
                }
                return d;
            } else if (Array.isArray(o) !== Array.isArray(n)) {
                d.add(p || '(root)');
                return d;
            }
            let ok = Object.keys(o),
                nk = Object.keys(n),
                ak = new Set([...ok, ...nk]);
            for (let k of ak) {
                let sp = p ? p + '.' + k : k;
                if (!(k in o)) d.add(sp + ' (added)');
                else if (!(k in n)) d.add(sp + ' (removed)');
                else {
                    let c = diffs(o[k], n[k], sp);
                    for (let x of c) d.add(x);
                }
            }
            return d;
        }

        // Recursive function to render the differences with highlighting
        function rSD(o, ds, t, p = '') {
            if (o === null || typeof o !== 'object') {
                let c = ds.has(p) ? (t === 'old' ? 'highlight-old removed' : 'highlight-new added') : '';
                return `<span class="highlight-diff ${c}">${eH(String(o))}</span>`;
            }
            if (Array.isArray(o)) {
                if (!o.length) return '<em class="text-muted">[] (empty)</em>';
                let h = '<ul class="json-list">';
                o.forEach((itm, i) => {
                    let cp = p + '[' + i + ']';
                    h += `<li><span class="json-key">[${i}]:</span> ${rSD(itm, ds, t, cp)}</li>`;
                });
                return h + '</ul>';
            }
            let k = Object.keys(o);
            if (!k.length) return '<em class="text-muted">{ } (empty)</em>';
            let h = `<table class="table table-sm table-bordered nested-table"><tbody>`;
            k.forEach(q => {
                let val = o[q],
                    cp = p ? (p + '.' + q) : q,
                    hi = ds.has(cp) ? (t === 'old' ? 'highlight-old removed' : 'highlight-new added') : '';
                if (val && typeof val === 'object') {
                    h += `<tr>
                            <td class="json-key ${hi}" style="width:200px;">${eH(q)}</td>
                            <td>
                                <button type="button" class="btn btn-sm btn-outline-secondary toggle-btn" data-target="#node_${cp}">
                                    <i class="fas fa-plus"></i> Toggle
                                </button>
                                <div id="node_${cp}" style="display:none;">${rSD(val, ds, t, cp)}</div>
                            </td>
                          </tr>`;
                } else {
                    let dv = val === null ? '<em>null</em>' : eH(String(val)),
                        vc = ds.has(cp) ? (t === 'old' ? 'highlight-old removed' : 'highlight-new added') : '';
                    h += `<tr>
                            <td class="json-key ${vc}" style="width:200px;">${eH(q)}</td>
                            <td class="json-value ${vc}">${dv}</td>
                          </tr>`;
                }
            });
            return h + '</tbody></table>';
        }

        // Function to parse and render old/new values with differences
        function pRS(o, n, t) {
            if (!o && t === 'old') return '<em>(No old data)</em>';
            if (!n && t === 'new') return '<em>(No new data)</em>';
            let oo, nn;
            try {
                oo = JSON.parse(o);
            } catch {
                oo = o;
            }
            try {
                nn = JSON.parse(n);
            } catch {
                nn = n;
            }
            let ds = diffs(oo, nn);
            if (t === 'old') {
                if (!oo) return '<em>(No old data)</em>';
                return rSD(oo, ds, 'old');
            } else if (t === 'new') {
                if (!nn) return '<em>(No new data)</em>';
                return rSD(nn, ds, 'new');
            }
            return '<em>No data</em>';
        }

        // Event Listener for View Changes Button
        $('#auditTable').on('click', '.view-changes', function() {
            let o = $(this).data('old') || '',
                n = $(this).data('new') || '';
            let ch = `<div class="changes-grid">
                        <div class="change-item">
                            <div class="field-name">Old Values</div>
                            <div class="field-value">${pRS(o, n, 'old')}</div>
                        </div>
                        <div class="change-item">
                            <div class="field-name">New Values</div>
                            <div class="field-value">${pRS(o, n, 'new')}</div>
                        </div>
                      </div>`;
            $('#changesContent').html(ch);

            // Calculate and display the detailed summary of changes
            let oo, nn;
            try {
                oo = JSON.parse(o);
            } catch {
                oo = o;
            }
            try {
                nn = JSON.parse(n);
            } catch {
                nn = n;
            }
            let ds = diffs(oo, nn);
            let changesArray = Array.from(ds);
            if (changesArray.length > 0) {
                let changesFormatted = changesArray.map(change => {
                    // Split the change description into field and action
                    let parts = change.split(' ');
                    let field = parts[0].replace(/\./g, ' → ');
                    let action = parts[1] || '';
                    return `<strong>${field}</strong> ${action}`;
                }).join(', ');
                $('#changesSummary').html('Changed fields: ' + changesFormatted);
            } else {
                $('#changesSummary').text('No changes detected.');
            }

            new bootstrap.Modal('#changesModal').show();
        });

        // Event Listener for Toggle Buttons in Changes Modal
        $(document).on('click', '.toggle-btn', function() {
            let t = $(this).data('target'),
                v = $(t).is(':visible');
            $(t).toggle();
            $(this).find('i').toggleClass('fa-plus fa-minus');
        });

        // Initialize and Show Toasts
        $('.toast').each(function() {
            let x = new bootstrap.Toast($(this), {
                delay: 5000
            });
            x.show();
        });
    });
</script>
</body>

</html>
<?php
require_once 'includes/footer.php';
ob_end_flush();
?>