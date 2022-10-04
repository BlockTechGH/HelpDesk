<?php
$this->start('title');
    echo __('New reply in ticket {0}', $subject);
$this->end();
?>

<?= $this->Html->script('fit_window'); ?>
<div id="feedback_form" class="row mt-3">
    <form 
        method="POST" 
        action="<?= $this->Url->build(['_name' => 'crm_settings_interface', '?' => ['DOMAIN' => $domain]]) ?>"
        enctype="multipart/form-data"
    >
        <?php foreach ($required as $property => $value): ?>
            <input type="hidden" name="<?=$property;?>" value='<?=$value;?>'>
        <?php endforeach;?>
        <input type="hidden" name="answer[from]" v-model="answer.from">
        <input type="hidden" name="answer[user_id]" v-model="answer.user_id">
        <div class="form-group">
            <h1><?=__('New reply in ticket {0}', $subject);?></h1>
        </div>
        <div class="form-group pt-1">
            <label for="answer[message]" class="form-label">{{ answer.from }}, {{ i18n.Reply }}</label>
            <textarea id="answer[message]" name="message" v-model="answer.message" class="form-control" rows="10"></textarea>
        </div>
        <div class="form-group pt-1" id="file_block">
            <button type="button" @click="upload" class="btn btn-primary">+</button>
            <label for="attachment" class="form-label">{{ i18n.Attachment }}</label>
            <input type="file" name="attachment[]" ref="file" class="form-control">
        </div>
        <div class="btn-group pt-1">
            <button type="submit" name="answer" class="btn btn-primary" @click="send">
                {{ i18n.Send }}
            </button>
        </div>
    </form>
</div>

<script>
    window.data = {
        ajax: "<?=$ajax;?>",
        required: <?=json_encode($required)?>,
        answer: <?=json_encode($answer)?>,
        ticket: <?=json_encode($ticket);?>,
        subject: '<?=$subject;?>',
        i18n: <?=json_encode([
            'Response' => __('Response'),
            'Reply' => __('your answer'),
            'EnterReplicaText' => __('Enter your answer here'),
            'Send' => __('Send'),
            'Attachment' => __('Attache file'),
        ]);?>,
    };
</script>

<script>
    new Vue({
        'el': '#feedback_form',
        'data': window.data,
        'methods': {
            send: function() {
                /*
                console.log("Send message", "Attached files: ", this.answer.attach.length);
                const formData = new FormData();
                formData.append('answer[from]', this.answer.from);
                formData.append('answer[message]', this.answer.message);
                formData.append('answer[user_id]', this.answer.user_id);
                formData.append('AUTH_ID', this.required.AUTH_ID);
                formData.append('AUTH_EXPIRES', this.required.AUTH_EXPIRES);
                formData.append('REFRESH_ID', this.required.REFRESH_ID);
                formData.append('member_id', this.required.member_id);
                formData.append('PLACEMENT_OPTIONS', this.required.PLACEMENT_OPTIONS);
                formData.append('ticket_id', this.ticket.id);
                var reader = new FileReader();
                for(let i = 0 ; i < this.answer.attach.length; i++) {
                    let file = this.answer.attach[i];
                    var name = file.name;
                    formData.append('attachment['+i+']', file);
                }
                const headers = { 'Content-Type': 'multipart/form-data' };
                fetch(this.ajax, {
                    method: "POST",
                    headers,
                    body: formData,
                }).then(async result => {
                    BX24.closeApplication();
                });
                */
                BX24.closeApplication();
            },
            upload: function() {
                /**
                const files = this.$refs.file.files;
                for(let i = 0 ; i < files.length; i++) {
                    let file = files[i];
                    this.answer.attach.push(file);
                }
                console.log("Files uploaded", this.answer.attach.length, files.length);
                 */
                $('#file_block').append("<input type='file' name='attachment[]' @change='upload' class='form-control'>");
                console.log('File input added');
            }
        }
    });
</script>