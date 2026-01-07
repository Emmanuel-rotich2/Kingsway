<?php
function renderTable(array $headers, array $rows, array $actions = [], string $id = 'table') {
?>
<table class="table table-hover table-bordered" id="<?= htmlspecialchars($id) ?>">
    <thead class="table-light">
        <tr>
            <?php foreach($headers as $header): ?>
                <th><?= htmlspecialchars($header) ?></th>
            <?php endforeach; ?>
            <?php if(!empty($actions)): ?>
                <th>Actions</th>
            <?php endif; ?>
        </tr>
    </thead>
    <tbody>
        <?php foreach($rows as $row): ?>
            <tr data-id="<?= $row[0] ?>">
                <?php foreach($row as $cell): ?>
                    <td><?= htmlspecialchars($cell) ?></td>
                <?php endforeach; ?>
                <?php if(!empty($actions)): ?>
                <td>
                    <div class="dropdown">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            Actions
                        </button>
                        <ul class="dropdown-menu">
                            <?php foreach($actions as $action): ?>
                                <li><a class="dropdown-item action-btn" href="#" data-action="<?= strtolower(str_replace(' ', '-', $action)) ?>" data-id="<?= $row[0] ?>"><?= htmlspecialchars($action) ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php
}
?>
