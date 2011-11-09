/**  LAYER GLOBAL EVENT LISTENERS  **/

var LayerGlobals = new Array();

function addGlobal(layerId,event,elementId){
		if(!LayerGlobals[layerId])LayerGlobals[layerId]={};
		eval('LayerGlobals[layerId].'+event+'= function(data){$("#'+elementId+'").trigger("'+event+'",data);}');
}



//Utilities



function convertTime(seconds){
	
	var m=Math.floor(seconds/60);
	var s=Math.floor(seconds%60);
	if(s<10) s="0"+s;
	return m+":"+s;
}

function deconvertTime(minutes,seconds){

	return 60*minutes+parseInt(seconds);
}

function getMinutes(seconds){

	return Math.floor(parseInt(seconds)/60.0);
}

function getSeconds(seconds){

	var s=Math.floor((seconds%60)*10)/10.0;
	if(s<10) s="0"+s;
	return s;

}

function isInt(x) {
	   var y=parseInt(x);
	   if (isNaN(y)) return false;
	   return x==y && x.toString()==y.toString();
}

function isPercent(x){

	return isInt(x)&&parseInt(x)<=100;

}

function deepCopy(p,c) {
	var c = c||{};
	for (var i in p) {
		if (typeof p[i] === 'object')
		{
			c[i] = (p[i].constructor === Array)?[]:{};
			deepCopy(p[i],c[i]);
		} else c[i] = p[i];
	}
	return c;
}