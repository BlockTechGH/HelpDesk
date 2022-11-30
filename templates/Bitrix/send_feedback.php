<?php
$this->start('title');
    echo __('New reply in ticket {0}', $subject);
$this->end();
?>

<div id="feedback_form" class="row m-3">
    <form 
        method="POST" 
        action="<?= $this->Url->build(['_name' => 'crm_settings_interface', '?' => ['DOMAIN' => $domain]]) ?>"
        enctype="multipart/form-data"
    >
        <input type="hidden" name="MAX_FILE_SIZE" :value="filesizeLimit" />
        <?php foreach ($required as $property => $value): ?>
            <input type="hidden" name="<?=$property;?>" value='<?=$value;?>'>
        <?php endforeach;?>
        <input type="hidden" name="answer[from]" v-model="answer.from">
        <input type="hidden" name="answer[user_id]" v-model="answer.user_id">
        <input type="hidden" name="ticket_id" v-model="ticket.id">
        <div class="form-group">
            <h1><?=__('New reply in ticket {0}', $subject);?></h1>
        </div>
        <div class="form-group pt-1">
            <label for="answer[message]" class="form-label">{{ answer.from }}, {{ i18n.Reply }}</label>
            <textarea id="answer[message]" name="answer[message]" v-model="answer.message" class="form-control" rows="10"></textarea>
            <div :class="{fade: !fileOversize, alert: true, 'alert-warning': true}">
                {{ fileControl }}
            </div>
        </div>
        <div class="form-group pt-1" id="file_block">
            <label for="attachment[]" class="form-label">{{ i18n.Attachment }}</label>
            <input type="file" name="attachment[]" ref="file" @change="upload" class="form-control-file" multiple>
        </div>
        <div :class="{alert: true, 'alert-success': true, 'alert-dismissible': true, fade: true, 'pt-1': true, show: saved}" role="alert">
            {{ i18n.Sent }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <div class="btn-group pt-1">
            <button type="submit" class="btn btn-primary" @click="send">
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
            'Sent' => __('Your message sent to client'),
        ]);?>,
        fileControl: "",
        fileOversize: false,
        filesizeLimit: 5*1024*1024,
        saved: false,
    };
</script>

<script>
    new Vue({
        el: '#feedback_form',
        data: window.data,
        methods: {
            send: function() {
                this.saved = true;
                    setTimeout(function () {
                        this.saved = false;
                        BX24.closeApplication();
                    }, 
                    5000
                );
            },
            upload: function() {
                let fileResultSize = 0;
                const files = this.$refs.file.files;
                for(let i = 0 ; i < files.length; i++) {
                    let file = files[i];
                    this.answer.attach.push(file);
                    fileResultSize += file.size;
                }
                this.fileOversize = fileResultSize > this.filesizeLimit;
                this.fileControl = "Files uploaded for " + fileResultSize  + " bytes. It is " + (this.fileOversize ? "oversize" : "ok");
                console.log(this.fileControl);
            }
        }
    });
</script>