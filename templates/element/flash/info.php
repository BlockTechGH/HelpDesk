<?php
/**
 * @var \App\View\AppView $this
 * @var array $params
 * @var string $message
 */
if (!isset($params['escape']) || $params['escape'] !== false) {
    $message = h($message);
}
?>
<?php if(isset($params['dismissible']) && $params['dismissible'] === true): ?>
    <div class="alert alert-success alert-dismissible fade show <?= $params['additionalClass'] ?? '' ?>" role="alert">
        <?= $message ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
<?php else: ?>
    <div class="alert alert-success <?= $params['additionalClass'] ?? '' ?>" role="alert">
        <?= $message ?>
    </div>
<?php endif; ?>