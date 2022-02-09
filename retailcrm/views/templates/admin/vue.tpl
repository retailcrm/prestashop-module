<script>window.jQuery || document.write('<script src="{$assets|escape:'htmlall':'UTF-8'}/js/vendor/jquery-3.4.0.min.js"><\/script>')</script>
<script>
    window.simlaData = {$vue|default:'{}'|@json_encode nofilter};
    console.log(window.simlaData);
    // connection data

    // delivery types

    // payment types

    // statuses

    // stocks

    // catalog

    // settings (sync, corps, numbers)
</script>

{include file='./module_messages.tpl'}
{include file='./module_translates.tpl'}
{include file='./../../../vue/index.html'}
