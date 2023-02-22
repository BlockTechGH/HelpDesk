const resolutionApp = new Vue(
{
    el: '#resApp',
    data: {},
    template: `
        <button type="button" v-on:click="addRes">QAZZZZ</button>
    `,
    computed:
    {
    },
    methods:
    {
        addRes: function()
        {
            console.log('add resolutions');
        }
    }
});
