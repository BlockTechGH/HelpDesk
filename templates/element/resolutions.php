<div id="resolutions">
    <div>
        <p>
            <button class="btn btn-link shadow-none" type="button" data-toggle="collapse" data-target="#collapseResolution" aria-expanded="false" aria-controls="collapseResolution">
                <?= __('Add New Resolution') ?>
            </button>
        </p>
        <div id="resolutionFlashMessageWrapper"></div>
        <div class="collapse" id="collapseResolution">
            <div class="card card-body">
                <form>
                    <div class="form-group">
                        <label for="resolutionText"><?= __('Resolution text') ?></label>
                        <textarea v-model="resolutionText" class="form-control" v-bind:class="{ 'is-invalid': isResoltionInvalid }" id="resolutionText" rows="5"></textarea>
                        <div class="invalid-feedback">
                            <?= __('Please fill this field') ?>
                        </div>
                    </div>
                    <div class="form-group row">
                        <div class="col-sm-10">
                            <button type="button" class="btn btn-primary" v-on:click="addResolutions" v-bind:disabled="resolutionAwaiting">
                                <span id="slaSpinner" role="status" aria-hidden="true" class="spinner-border spinner-border-sm mr-2" v-if="resolutionAwaiting"></span>
                                <svg v-if="!resolutionAwaiting" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-file-earmark-plus" viewBox="0 0 16 16">
                                    <path d="M8 6.5a.5.5 0 0 1 .5.5v1.5H10a.5.5 0 0 1 0 1H8.5V11a.5.5 0 0 1-1 0V9.5H6a.5.5 0 0 1 0-1h1.5V7a.5.5 0 0 1 .5-.5z"/>
                                    <path d="M14 4.5V14a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V2a2 2 0 0 1 2-2h5.5L14 4.5zm-3 0A1.5 1.5 0 0 1 9.5 3V1H4a1 1 0 0 0-1 1v12a1 1 0 0 0 1 1h8a1 1 0 0 0 1-1V4.5h-2z"/>
                                </svg>
                                <?= __('Add') ?>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="mt-3">
        <div v-for="(resolution, index) in resolutions" class="border rounded p-3 mb-2">
            <p class="ticket-customer-date">{{ resolution.fullName }} {{ resolution.formattedTime }}</p>
            <div v-html="resolution.formattedText"></div>
        </div>
    </div>
</div>