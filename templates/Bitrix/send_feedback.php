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
        <div class="form-group">
            <h1><?=__('New reply in ticket {0}', $subject);?></h1>
        </div>
        <div class="form-group pt-1">
            <label for="message" class="form-label">{{ answer.from }}, {{ i18n.Reply }}</label>
            <textarea id="message" name="message" v-model="answer.message" class="form-control" rows="10"></textarea>
        </div>
        <div class="form-group pt-1">
            <label for="attachment" class="form-label">{{ i18n.Attachment }}</label>
            <input type="file" name="attachment" id="attachment" @change="upload($event)" class="form-control">
        </div>
        <div class="btn-group pt-1">
            <button type="button" name="answer" class="btn btn-primary" @click="send">
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
                console.log("Send message");
                const parameters = Object.assign(
                    {
                        answer: this.answer,
                        ticket_id: this.ticket.id,
                    },
                    this.required
                );

                const headers = { 'Content-Type': 'application/json' };
                fetch(this.ajax, {
                    method: "POST",
                    headers,
                    body: JSON.stringify(parameters),
                }).then(async result => {
                    BX24.closeApplication();
                });
            },
            upload: function() {
            //    this.answer.attachment = this.$refs.attachment.files[0];
            }
        }
    });
</script>