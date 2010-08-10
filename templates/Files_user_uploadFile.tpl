<form class="z-form" id="updateFile" action="{modurl modname="Files" func="uploadfile"}" method="post" enctype="multipart/form-data">
    <div>
        <input type="hidden" name="authid" value="{insert name="generateauthkey" module="Files"}"/>
        <input type="hidden" name="folder" value="{$folder|safetext}" />
        <input type="hidden" name="external" value="{$external|safetext}" />
		<input type="hidden" name="hook" value="{$hook}" />
        <fieldset>
            <legend>{gt text="Upload file"}</legend>
            <div class="z-formrow">
                <label for="newFolder">{gt text="File name"}</label>
                <input type="file" name="newFile" id="newFile" size="20">
            </div>
            <p class="z-formnote">{gt text="Allowed extensions (comma separated list)"} {$extensions|safetext}</p>
            <div class="z-formbuttons">
                <a href="javascript:submitUpdateFile();">{img modname='core' src='button_ok.gif' set='icons/small' altml='true' titleml='true' __alt="Accept" __title="Accept"}</a>
                <a href="{modurl modname="Files" type=$type func=$func folder=$folder|replace:'/':'|' hook=$hook}">{img modname='core' src='button_cancel.gif' set='icons/small' altml='true' titleml='true' __alt="Cancel" __title="Cancel"}</a>
            </div>
        </fieldset>
    </div>
</form>