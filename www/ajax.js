function XMLHttpHandle()
{
	var ajaxRequest;
	try {
		ajaxRequest = new XMLHttpRequest();
	} catch (e) {
		try {
			ajaxRequest = new ActiveXObject("MSXML2.XMLHTTP.3.0");
		} catch (e) {
			ajaxRequest = false;
		}
	}
	return ajaxRequest;
}

function updateClarifications(ajaxtitle)
{
	var handle = XMLHttpHandle();
	if (!handle) {
		return;
	}
	handle.onreadystatechange = function() {
		if (handle.readyState == 4) {
			var elem = document.getElementById('menu_clarifications');
			var cnew = handle.responseText;
			var newstr = ''
			if (cnew == 0) {
				elem.className = null;
			} else {
				newstr = ' ('+cnew+' new)';
				elem.className = 'new';
			}
			elem.innerHTML = 'clarifications' + newstr;
			if(ajaxtitle) {
				document.title = ajaxtitle + newstr;
			}
		}
	}
	handle.open("GET", "update_clarifications.php", true);
	handle.send(null); 
}

function editTcDesc(descid)
{
	var node = document.getElementById('tcdesc_' + descid);
	var elem = document.createElement('textarea');
	elem.innerHTML = node.innerHTML;
	elem.setAttribute("cols", 50);
	elem.setAttribute("rows", 2);
	elem.setAttribute("name","description[" + descid + "]");
	node.innerHTML = "";
	node.setAttribute("onclick", "");
	node.appendChild(elem);
}
