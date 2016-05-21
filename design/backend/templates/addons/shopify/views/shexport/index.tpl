{capture name="formbox"}

    <form action="{""|fn_url}" name="shexport" method="get">
        <h4>Export products to Shopwave</h4>
        <div>Use Wget from command line if there are a lot of products</div>
        <div>wget ...</div>
        <!--<input type="hidden" value="Y" name="export">-->
        <div class="buttons-container ty-search-form__buttons-container">
            {include file="buttons/button.tpl" but_meta="ty-btn__secondary" but_text=__("Export") but_name="dispatch[shexport.products]"}
        </div>
    </form>
    <p>
        Logs at var/shopify.log
    </p>
{/capture}

{capture name="mainbox"}
    {$smarty.capture.formbox|default:"&nbsp;" nofilter}
{/capture}

{include file="common/mainbox.tpl" title="Export to Shopify store" content=$smarty.capture.mainbox content_id="shexport"}