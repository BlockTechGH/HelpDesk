<?php $this->start('script');?>
    <script src="https://cdn.jsdelivr.net/npm/bs-custom-file-input/dist/bs-custom-file-input.min.js"></script>
<?php $this->end();?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.3/font/bootstrap-icons.css">

<form id="fileUploadForm">
    <div class="row">
    
        <div class="custom-file col-10">
            <label class="custom-file-label" for="fileUpload"><?= __('Select file(s)') ?></label>
            <input type="file" class="custom-file-input" multiple id="fileUpload" v-on:change.prevent="selectedFiles">
        </div>
        <button type="button" v-bind:disabled="isFileUploadButtonDisaled" class="btn btn-primary ml-2" v-on:click.prevent="uploadFiles">
            <span role="status" aria-hidden="true" class="spinner-border spinner-border-sm mr-2" v-if="isUploadProcess"></span>
            <?= __('Upload') ?>
        </button>
        <button type="button" v-bind:disabled="isFileUploadButtonDisaled" class="btn btn-info ml-2" v-on:click.prevent="clearForm">
            <?= __('Clear') ?>
        </button>
    </div>
</form>

<div class="row mt-4">
    <table class="table" v-if="files.length">
        <thead>
            <th scope="col">#</th>
            <th scope="col"><?= __('Name') ?></th>
            <th scope="col" class="text-center"><?= __('Actions') ?></th>
        </thead>
        <tbody>
            <tr v-for="(file, index) in files">
                <th>{{ index + 1 }}</th>
                <th>{{ file.NAME }}</th>
                <th class="text-center">
                    <a class="btn btn-primary btn-sm" v-bind:href="file.URL"><?= __('Download') ?></a>
                    <button class="btn btn-danger btn-sm" v-on:click.prevent="deleteFile(file, index)"><?= __('Delete') ?></button>
                </th>
            </tr>
        </tbody>
    </table>
</div>
<script>
    $(document).ready(function()
    {
        bsCustomFileInput.init();
    });
</script>

<div id="notification" class="toast mt-3 mr-3" role="alert" aria-live="assertive" aria-atomic="true" data-delay="4000" style="position: absolute; top: 0; right: 0;">
    <div class="toast-header">
        <i id="notificationIcon" class="bi bi-check-square-fill text-success"></i>
        <strong class="mr-auto ml-2"><?= __('Helpdesk') ?></strong>
        <button type="button" class="ml-2 mb-1 close" data-dismiss="toast" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
    <div id="toastText" class="toast-body"></div>
</div>