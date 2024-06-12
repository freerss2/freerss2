/* ----------------- *\
   Service functions
    JavaScript code
\* ----------------- */

// -------------------------( date formatting )-------------------------

// zero-padding for a number
function pad2(n) { return n < 10 ? '0' + n : n }

// format JS date object as "YYYY-MM-DD hh:mm:ss"
function dateFormatStd(date) {
  return date.getFullYear().toString() + '-' +
         pad2(date.getMonth() + 1) + '-' +
         pad2( date.getDate()) + ' ' +
         pad2( date.getHours() ) + ':' +
         pad2( date.getMinutes() ) + ':' +
         pad2( date.getSeconds() );
}
/* Usage example:



function debugDate() {
  var date = new Date();
  window.alert(dateFormatStd(date));
}
*/

// ---------------------( commuincation with server )-------------------

// Send synchronous HTTP request and get the result as text
function httpGet(theUrl)
{
    var xmlHttp = new XMLHttpRequest();
    xmlHttp.open("GET", theUrl, false); // false for synchronous request
    try {
      xmlHttp.send(null);
    } catch(exception) {
      return "Error: "+exception.name+" - "+exception.message;
    }
    return xmlHttp.responseText;
}

// Send asynchronous HTTP request
function httpGetAsync(theUrl, callback)
{
    var xmlHttp = new XMLHttpRequest();
    xmlHttp.onreadystatechange = function() { 
        if (xmlHttp.readyState == 4) {
          if (xmlHttp.status == 200) {
            callback(xmlHttp.responseText);
          } else {
            msg = (xmlHttp.status) ? (xmlHttp.status+" - "+xmlHttp.responseText) : "network failure";
            callback("Error: "+msg);
          }
       }
    }
    xmlHttp.open("GET", theUrl, true); // true for asynchronous 
    xmlHttp.send(null);
}

// Send synchronous HTTP POST request and get the result as text
function httpPost(theUrl, postData)
{
    var xmlHttp = new XMLHttpRequest();
    xmlHttp.open("POST", theUrl, false); // false for synchronous request
    xmlHttp.setRequestHeader('Content-Type', 'application/json');
    xmlHttp.send(postData);
    return xmlHttp.responseText;
}

// fixing encoding of response
function filterResponse(reply) {
  return reply.replace(/^\uFEFF/gm, "").replace(/^\u00EF?\u00BB\u00BF/gm,"").replace(/^\uFEFF/gm, "");
}

// ---------------------( HTML entitles encode/decode )--------------------------

function encodeHTMLEntities(rawStr) {
  return rawStr.replace(/[\u00A0-\u9999<>\&]/g, ((i) => `&#${i.charCodeAt(0)};`));
}

function decodeHTMLEntities(rawStr) {
  return rawStr.replace(/&#(\d+);/g, ((match, dec) => `${String.fromCharCode(dec)}`));
}

// ---------------------( HTML entitle tags decode )--------------------------

function htmlDecode(input) {
  var doc = new DOMParser().parseFromString(input, "text/html");
  return doc.documentElement.textContent;
}

// ---------------------( building URL from form data )--------------------------

// re-build form submit URL
function buildFormURL(form) {
    var action = form.action;
    var args = getFormArgs(form);
    return action + '?' + args;
}

// read form args
// @return: string of args encoded as URI components
function getFormArgs(form) {
    var elements = form.elements;
    var args = [];
    for (var i = 0, element; element = elements[i++];) {
      if ( element.type == 'radio' && ! element.checked) {
        continue;
      }
      args[i] = element.name + '=' + encodeURIComponent(element.value);
    }
    return args.join("&");
}

// decode from given string URL arguments
// @usage:
// var query = getQueryParams(document.location.search);
// alert(query.foo);
function getQueryParams(url) {

  // get query string from url (optional) or window
  var queryString = url ? url.split('?')[1] : window.location.search.slice(1);

  // we'll store the parameters here
  var obj = {};

  // if query string exists
  if (queryString) {

    // stuff after '#' is not part of query string, so get rid of it
    queryString = queryString.split('#')[0];

    // split our query string into its component parts
    var arr = queryString.split('&');

    for (var i=0; i<arr.length; i++) {
      // separate the keys and the values
      var a = arr[i].split('=');

      // in case params look like: list[]=thing1&list[]=thing2
      var paramNum = undefined;
      var paramName = a[0].replace(/\[\d*\]/, function(v) {
        paramNum = v.slice(1,-1);
        return '';
      });

      // set parameter value (use 'true' if empty)
      var paramValue = typeof(a[1])==='undefined' ? true : a[1];

      // (optional) keep case consistent
      paramName = paramName.toLowerCase();
      // paramValue = paramValue.toLowerCase();

      // if parameter name already exists
      if (obj[paramName]) {
        // convert value to array (if still string)
        if (typeof obj[paramName] === 'string') {
          obj[paramName] = [obj[paramName]];
        }
        // if no array index number specified...
        if (typeof paramNum === 'undefined') {
          // put the value on the end of the array
          obj[paramName].push(paramValue);
        }
        // if array index number specified...
        else {
          // put the value at that index number
          obj[paramName][paramNum] = paramValue;
        }
      }
      // if param name doesn't exist yet, set it
      else {
        obj[paramName] = paramValue;
      }
    }
  }

  return obj;
}

// Select range in input element (compatible with different browser engines)
// @param element: DOM element
// @param begin: initial selection index in text
// @param end: end selection index in text
function select_sub_string(element, begin, end)
{
	if (element.setSelectionRange)
	{
		element.setSelectionRange(begin, end);
	}
	else if (element.createTextRange)
	{
		var range = element.createTextRange();
		range.moveStart("character", begin);
		range.moveEnd("character", end);
		range.select();
	}
} // select_sub_string


// bind keyboard key on given element to function
// @param elm_id: ID of DOM element to accept keystroke
// @param expected_key_name: expected key name
// @param fn: function to be triggered on key match
function bindKeyForElement(elm_id, expected_key_name, fn) {
  var elm = document.getElementById(elm_id);
  if (! elm) { return; }
  elm.addEventListener("keydown", function (event) {
      var event_key = event.key;
      if (event_key == expected_key_name) { fn(); }
    });
}

// If URL contains auth_token=NNNN, save it in localStorage as "authToken"
function setLoginAuthToken() {
  var mobile_client = getCookie('mobile_client') ? '1' : '0';
  window.localStorage.setItem('mobileClient', mobile_client);
  var url = new URL(window.location.href);
  var auth_token = url.searchParams.get("auth_token");
  if (! auth_token) { return; }
  window.localStorage.setItem('authToken', auth_token);
}

function setCookie(name,value,days) {
    var expires = "";
    if (days) {
        var date = new Date();
        date.setTime(date.getTime() + (days*24*60*60*1000));
        expires = "; expires=" + date.toUTCString();
    }
    document.cookie = name + "=" + (encodeURIComponent(value) || "")  + expires + "; path=/";
}

function getCookie(name) {
    var nameEQ = name + "=";
    var ca = document.cookie.split(';');
    for(var i=0;i < ca.length;i++) {
        var c = ca[i];
        while (c.charAt(0)==' ') c = c.substring(1,c.length);
        if (c.indexOf(nameEQ) == 0) return decodeURIComponent(c.substring(nameEQ.length,c.length));
    }
    return null;
}

function eraseCookie(name) {
    document.cookie = name +'=; Path=/; Expires=Thu, 01 Jan 1970 00:00:01 GMT;';
}
