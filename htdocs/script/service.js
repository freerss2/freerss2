/* ----------------- *\
   Service functions
    JavaScript code
\* ----------------- */


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

