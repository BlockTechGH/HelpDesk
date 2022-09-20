<?= $this->Html->script('fit_window'); ?>
<div id="setting_container" class="row">
    <div class="col-2">
        <div class="nav flex-column nav-pills" id="myTab" role="tablist" aria-orientation="vertical">
        <?php foreach($tabs as $tabName => $tab): ?>
            <button class="nav-link<?= $tab['active'] ? ' active' : '' ?>" data-toggle="tab" type="button" role="tab" 
                <?= $tab['active'] ? 'aria-selected="true"' : ''?> 
                id="<?=$tabName;?>-tab" 
                data-target="#<?=$tabName;?>" 
                aria-controls="<?=$tabName;?>"
            >
                <?=__($tab['title']);?>
            </button>
        <?php endforeach; ?>
        </div>
    </div>
    <div class="col-10">
        <div class="tab-content" id="myTabContent">
        <?php foreach($options as $tab => $tabOptions): ?>
            <div class="tab-pane fade show active" id="<?=$tab;?>" role="tabpanel" aria-labelledby="source-tab">
                <form method="POST" action="<?= $this->Url->build(['_name' => 'crm_settings_interface', '?' => ['DOMAIN' => $domain]]) ?>">
                    <?php foreach($tabOptions as $option): ?>
                        <div class="form-group">
                            <label for="<?=$option['opt'];?>"><?=$option['opt'];?></label>
                            <input class="form-control" name="<?=$option['opt'];?>" value="<?=$option['value'];?>"/>
                        </div>
                    <?php endforeach;?>

                    <input type="hidden" name="AUTH_ID" value="<?=$authId?>" />
                    <input type="hidden" name="AUTH_EXPIRES" value="<?=$authExpires?>" />
                    <input type="hidden" name="REFRESH_ID" value="<?=$refreshId?>" />
                    <input type="hidden" name="member_id" value="<?=$memberId?>" />

                    <button type="submit" name="saveSettings" class="btn btn-primary"><?= __('Save') ?></button>
                </form>
            </div>
        <?php endforeach; ?>
        </div>
    </div>
</div>
