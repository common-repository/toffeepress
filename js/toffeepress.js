
var tp_process_status = false;
var tp_current_item = 0;
var tp_running = 0;
var tp_running_limit = 1;
var tp_timer_interval = 0;
var tp_time_remaining_current = 0;

function tpBytesToSize(bytes){
	var sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
	if (bytes == 0) return '0 Byte';
	var i = parseInt(Math.floor(Math.log(bytes) / Math.log(1024)));
	return Math.round(bytes / Math.pow(1024, i), 2) + ' ' + sizes[i];
}

function tpNumberFormat(fltNumber,intDecimals){
	return (intDecimals == 0 || intDecimals == '' || intDecimals == null) ? Math.floor(fltNumber) : fltNumber.toPrecision(parseInt(intDecimals)+1);
}

function tpSecondsFormat(intSeconds){
	var sec_num = parseInt(intSeconds, 10); // don't forget the second param
	var hours   = Math.floor(sec_num / 3600);
	var minutes = Math.floor((sec_num - (hours * 3600)) / 60);
	var seconds = sec_num - (hours * 3600) - (minutes * 60);

	if (hours   < 10) {hours   = "0"+hours;}
	if (minutes < 10) {minutes = "0"+minutes;}
	if (seconds < 10) {seconds = "0"+seconds;}

	return (hours==='00' ? '' : hours+':')+minutes+':'+seconds;
}

function tpPing(host, pong){
	var started = new Date().getTime();
	var http = new XMLHttpRequest();
	http.open("GET", "https://toffeepress.twistphp.com/api/", /*async*/true);
	http.onreadystatechange = function() {
		if (http.readyState == 4) {
			var ended = new Date().getTime();

			var milliseconds = ended - started;

			if (pong != null) {
				pong(milliseconds);
			}
		}
	};
	try {
		http.send(null);
	} catch(exception) {
		// this is expected
	}
}

function tpPong(milliseconds){
	jQuery('.ping').html(milliseconds+'ms');
}

function tpRemainingTime(){

	//Make the countdown a bit smoother
	if(tp_time_remaining_current == 0 || (tp_time_remaining <= (tp_time_remaining_current-5)) || (tp_time_remaining >= (tp_time_remaining_current+5))){
		tp_time_remaining_current = tp_time_remaining
	}
	if(tp_time_remaining_current > 0){
		tp_time_remaining_current = Math.floor(tp_time_remaining_current)-1;
	}
	jQuery('.remainingTime').html(tpSecondsFormat(tp_time_remaining_current));
}

function tpStartProcess(){
	tp_process_status = true;
	tpNextAttachment();
	jQuery('a.cancel').show();
	jQuery('a.finished').hide();
	clearInterval(tp_timer_interval);
	tp_timer_interval = setInterval(tpRemainingTime,1000);
}

function tpStopProcess(){
	jQuery('.log').html('<span class="OK">Process has been stopped!</span>'+jQuery('.log').html());
	tp_process_status = false;
	jQuery('a.cancel').hide();
	jQuery('a.finished').show();
	clearInterval(tp_timer_interval);
}

function tpFinishProcess(){
	jQuery('.log').html('<span class="OK">Process has finished successfully!</span>'+jQuery('.log').html());
	tp_process_status = false;
	jQuery('a.cancel').hide();
	jQuery('a.finished').show();
	clearInterval(tp_timer_interval);
}

function tpNextAttachment(){
	if(tp_process_status){

		if(tp_compress_list.length === tp_current_item){
			tpFinishProcess();
		}else{
			while(tp_running < tp_running_limit && tp_current_item < tp_compress_list.length){
				tpCompressAttachment(tp_compress_list[tp_current_item].id,tp_compress_list[tp_current_item].s);
				tp_current_item++;
			}
		}
	}
}

function tpCompressAttachment(attachmentID,attachmentSize){

	jQuery.ajax({
		url : tp_admin_ajax,
		type : 'post',
		data : 'action='+tp_type+'&attachment='+attachmentID+'&size='+attachmentSize,
		beforeSend: function(){
			tp_running++;
			tp_currently_compressing++;
			jQuery('.compressing').html(tp_currently_compressing);
		},
		success : function(response){

			if(response.status){

				tp_currently_compressing--;
				tp_bytes_saved += response.saving_bytes;
				tp_files_compressed++;
				progressPercentage = (100/tp_files_to_compress)*tp_files_compressed;

				if(progressPercentage < 10){
					progressPercentage = tpNumberFormat(progressPercentage,1);
				}else{
					progressPercentage = tpNumberFormat(progressPercentage,0);
				}

				intTimePerFile = (((new Date().getTime()/1000)-tp_time_started) / tp_files_compressed);
				tp_time_remaining = intTimePerFile*(tp_files_to_compress-tp_files_compressed);

				jQuery('#percentageStroke').attr('stroke-dasharray',progressPercentage+',100');
				jQuery('#percentageTextbox1').html(progressPercentage+'%');
				jQuery('#percentageTextbox2').html(tp_files_compressed+' of '+tp_files_to_compress+' images');
				jQuery('.bytes_saved').html(tpBytesToSize(tp_bytes_saved));
				jQuery('.compressing').html(tp_currently_compressing);

			}else{
				tp_currently_compressing--;
				tp_files_failed++;
				jQuery('.compressing').html(tp_currently_compressing);
				jQuery('.log').html('<span class="error">['+attachmentID+'] '+response.message+'</span>'+jQuery('.log').html());
			}

			tp_running--;
			tpNextAttachment();
		},
		error : function(){
			tpStopProcess();
			alert('Process has stopped to to an unknown error');
		}
	});
}


var tp_cleanup_process_status = false;
var tp_cleanup_current_item = 0;
var tp_cleanup_prerun = 20;

function tpStartCleanupProcess(){
	tp_cleanup_process_status = true;
	tpNextCleanupAttachment();
}

function tpStopCleanupProcess(){
	jQuery('.log').html('<span class="OK">Process has been stopped!</span>'+jQuery('.log').html());
	tp_cleanup_process_status = false;
}

function tpFinishCleanupProcess(){
	jQuery('.log').html('<span class="OK">Process has finished successfully!</span>'+jQuery('.log').html());
	tp_cleanup_process_status = false;
}

function tpNextCleanupAttachment(){
	if(tp_cleanup_process_status){

		if(tp_cleanup_list.length === tp_cleanup_current_item){
			tpFinishCleanupProcess();
		}else{
			var cleanup_params = '';
			var cleanup_collection_count = 0;

			while(cleanup_collection_count < tp_cleanup_prerun && tp_cleanup_current_item < tp_cleanup_list.length){

				cleanup_params += "&attachment[]="+tp_cleanup_list[tp_cleanup_current_item].id+','+tp_cleanup_list[tp_cleanup_current_item].s;
				tp_cleanup_current_item++;
				cleanup_collection_count++;
			}

			tpCleanupAttachment(cleanup_params,cleanup_collection_count);

		}
	}
}

function tpCleanupAttachment(cleanup_params,cleanup_count){

	jQuery('.cleaning').html(parseInt(jQuery('.cleaning').html())+cleanup_count);

	jQuery.ajax({
		url : tp_admin_ajax,
		type : 'post',
		data : 'action=tp_cleanup'+cleanup_params,
		success : function(response){

			if(response.status){
				jQuery('.files_to_cleanup').html(parseInt(jQuery('.files_to_cleanup').html())-cleanup_count);
				jQuery('.files_cleaned').html(parseInt(jQuery('.files_cleaned').html())+cleanup_count);
				jQuery('.cleaning').html(parseInt(jQuery('.cleaning').html())-cleanup_count);
			}else{
				jQuery('.cleaning').html(parseInt(jQuery('.cleaning').html())-cleanup_count);
				jQuery('.files_to_cleanup').html(parseInt(jQuery('.files_to_cleanup').html())-cleanup_count);
				jQuery('.log').html('<span class="error">'+response.message+'</span>'+jQuery('.log').html());
			}

			tpNextCleanupAttachment();
		},
		error : function(){
			tpStopCleanupProcess();
			alert('Process has stopped to to an unknown error');
		}
	});
}