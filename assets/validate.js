jQuery(document).ready(function($) {
	
	$('#validate_ximalaya a').click(function(e) {
		jQuery.get($(this).attr("href"), function(json){
			json = eval("("+json+")");
			if (!json.err) {
				alert(validate.loginSuccess);
			}else if(json.err == 51) {
				$('#realmobile').val(json.data.mobile);
				$('#checkkey').val(json.data.checkkey);
				tb_show(validate.send_smscode, '#TB_inline?height=220&width=220&inlineId=send-smscode');
			}else{
				alert(json.msg)
			}
		});
		return false;
	});
	$('#smscode-send').click(function(e) {
		var smscode = $('#smscode').val();
		var mobile = $('#realmobile').val();
		var checkkey = $('#checkkey').val();
		jQuery.get($(this).data("url"), {smscode:smscode, mobile:mobile, checkkey:checkkey}, function(json){
			json = eval("("+json+")");
			if (!json.err) {
				alert(validate.correct);
			}else{
				alert(json.msg)
			}
			console.log(json);
		});
		tb_remove();
	})
    
});
