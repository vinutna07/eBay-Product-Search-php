<?php
if (isset($_POST["find"])) {
    // extract the form data
    $url = "http://svcs.ebay.com/services/search/FindingService/v1?";
    $url = $url . "OPERATION-NAME=findItemsAdvanced&";
    $url = $url . "SERVICE-VERSION=1.0.0&";
    $url = $url . "SECURITY-APPNAME=ArvindBr-asd-PRD-e16de56b6-46891f64&";
    $url = $url . "RESPONSE-DATA-FORMAT=JSON&";
    $url = $url . "REST-PAYLOAD&";
    $url = $url . "paginationInput.entriesPerPage=20&";
    foreach ($_POST as $key => $value) {
        if ($key !== "find" ) {
            $url = $url . str_replace ("_",".",$key) . "=" . urlencode($value) . "&";
        }
    }
    $json = file_get_contents(rtrim($url, "&"));
    $json_result = json_decode($json, true);
    
    // print("<pre>".print_r($json_result['findItemsAdvancedResponse'][0]['searchResult'][0]['item'], true)."</pre>");

    $response = array();

    foreach ($json_result['findItemsAdvancedResponse'][0]['searchResult'][0]['item'] as $item) {
        // echo "herher";
        // print_r($item);
        $response_iter = array();
        $response_iter['itemId'] = (isset($item['itemId'][0]) ? $item['itemId'][0] : "N/A");
        $response_iter['title'] = (isset($item['title'][0]) ? $item['title'][0] : "N/A");
        $response_iter['galleryURL'] = (isset($item['galleryURL'][0]) ? $item['galleryURL'][0] : "N/A");
        $response_iter['postalCode'] = (isset($item['postalCode'][0]) ? $item['postalCode'][0] : "N/A");
        // print_r($item['shippingInfo']);
        $shippingInfo = $item['shippingInfo'][0]['shippingServiceCost'][0]['__value__'];

        if (!isset($shippingInfo)) {
            $response_iter['shippingCost'] = "N/A";
        } else if ($shippingInfo == "0.0") {
            $response_iter['shippingCost'] = "Free";
        } else {
            $response_iter['shippingCost'] = $shippingInfo;
        }


        $response_iter['currPrice'] = (isset($item['sellingStatus'][0]['currentPrice'][0]['__value__']) ? $item['sellingStatus'][0]['currentPrice'][0]['__value__'] : "N/A");

        if (!isset($item['condition'][0]['conditionDisplayName'][0])) {
            $response_iter['condition'] = "N/A";
        } else {
            $response_iter['condition'] = $item['condition'][0]['conditionDisplayName'][0];
        }


        $response[] = $response_iter;
    }
    echo json_encode($response);

} else if(isset($_POST["itemId"])){

    /* http://open.api.ebay.com/shopping?callname=GetSingleItem&responseencoding=JSO
    N&appid=[APPID]&siteid=0&version=967&ItemID=[ITEMID]&IncludeSelector=Descript
    ion,Details,ItemSpecifics */

    $url = "http://open.api.ebay.com/shopping?";
    $url = $url . "callname=GetSingleItem&";
    $url = $url . "responseencoding=JSON&appid=ArvindBr-asd-PRD-e16de56b6-46891f64&";
    $url = $url . "siteid=0&version=967&";
    $url = $url . "ItemID=" . $_POST["itemId"];
    $url = $url . "&IncludeSelector=Description,Details,ItemSpecifics";
    
    $json = file_get_contents(rtrim($url, "&"));
    $json_result = json_decode($json, true);
    
    $response = array();
    
    if($json_result['Ack'] == "Failure"){
        $response['Ack'] = "Failure";
        $response['Error'] = $json_result["Errors"][0]["ShortMessage"];
    }
    else{
        $response['Ack'] = "Success";
        $response['Description'] = $json_result['Item']['Description'];
        $response['PictureURL'] = $json_result['Item']['PictureURL'];
        $response['Title'] = $json_result['Item']['Title'];
        $response['PictureURL'] = $json_result['Item']['PictureURL'][0];
        $response['Subtitle'] = $json_result['Item']['Subtitle'];
        $response['Price'] = $json_result['Item']['CurrentPrice']['Value'];
        $response['Location'] = $json_result['Item']['Location'];
        $response['Seller'] = $json_result['Item']['Seller']['UserID'];
        if($json_result['Item']['ReturnPolicy']['ReturnsAccepted'] == "ReturnsNotAccepted"){
            $response['ReturnPolicy'] = "Returns Not Accepted";        
        }
        else if(isset($json_result['Item']['ReturnPolicy']['ReturnsAccepted'])){
            $response['ReturnPolicy'] = $json_result['Item']['ReturnPolicy']['ReturnsAccepted'] . " within " . $json_result['Item']['ReturnPolicy']['ReturnsWithin'];
        }
        $feature_list = array();
        foreach($json_result['Item']['ItemSpecifics']['NameValueList'] as $feature){
            if(sizeof($feature['Value']) == 1){
                $feature_list[$feature['Name']] = $feature['Value'][0];
            }
            else{
                $feature_list[$feature['Name']] = "";
                foreach($feature['Value'] as $value){
                    $feature_list[$feature['Name']] = $feature_list[$feature['Name']] . $value . "<br>";
                }
                $feature_list[$feature['Name']] = preg_replace("/<br>$/","",$feature_list[$feature['Name']]);
            }
        }
        $response['ItemSpecifics'] = $feature_list;
        
        $url = "http://svcs.ebay.com/MerchandisingService?";
        $url = $url . "OPERATION-NAME=getSimilarItems&SERVICE-NAME=MerchandisingService&SERVICE-VERSION=1.1.0&";
        $url = $url . "CONSUMER-ID=ArvindBr-asd-PRD-e16de56b6-46891f64&";
        $url = $url . "RESPONSE-DATA-FORMAT=JSON&REST-PAYLOAD&";
        $url = $url . "itemId=" . $_POST['itemId'];
        $url = $url . "&maxResults=8";

        $json = file_get_contents(rtrim($url, "&"));
        $json_result = json_decode($json, true);

        // print_r($json_result['getSimilarItemsResponse']['itemRecommendations']['item']);
        $similar_items = array();
        foreach ($json_result['getSimilarItemsResponse']['itemRecommendations']['item'] as $item) {
            $similar_iter = array();
            $similar_iter['itemId'] = $item['itemId'];
            $similar_iter['title'] = $item['title'];
            $similar_iter['imageURL'] = $item['imageURL'];
            $similar_iter['price'] = "$ " . $item['buyItNowPrice']['__value__'];
            $similar_items[] = $similar_iter;
        }
        $response['SimilarItems'] = $similar_items;

        $url = "http://svcs.ebay.com/MerchandisingService?";
        $url = $url . "OPERATION-NAME=getSimilarItems&SERVICE-NAME=MerchandisingService&SERVICE-VERSION=1.1.0&";
        $url = $url . "CONSUMER-ID=ArvindBr-asd-PRD-e16de56b6-46891f64&";
        $url = $url . "RESPONSE-DATA-FORMAT=JSON&REST-PAYLOAD&";
        $url = $url . "itemId=" . $_POST['itemId'];
        $url = $url . "&maxResults=8";

        $json = file_get_contents(rtrim($url, "&"));
        $json_result = json_decode($json, true);

        // print_r($json_result['getSimilarItemsResponse']['itemRecommendations']['item']);
        $similar_items = array();
        foreach ($json_result['getSimilarItemsResponse']['itemRecommendations']['item'] as $item) {
            $similar_iter = array();
            $similar_iter['itemId'] = $item['itemId'];
            $similar_iter['title'] = $item['title'];
            $similar_iter['imageURL'] = $item['imageURL'];
            $similar_iter['price'] = "$ " . $item['buyItNowPrice']['__value__'];
            $similar_items[] = $similar_iter;
        }
        $response['SimilarItems'] = $similar_items;
    }

    echo json_encode($response);

}else {
    ?>
<html>

<head>
    <!-- <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.7.2/css/all.css"
        integrity="sha384-fnmOCqbTlWIlj8LyTjo7mOUStjsKC4pOpQbqyi7RrhN7udi9RwhKkMHpvLbHG9Sr" crossorigin="anonymous"> -->
    <style>
    #form {
        width: 550px;
        margin: 30px auto;
        border: 1px solid #ccc;
        padding: 20px;
        padding-top: 20px;
        background: #eee;
    }

    #buttons {
        margin-left: auto;
        margin-right: auto;
        width: 300px;
        text-align: center;
    }

    #buttons>input {
        background: #fff;
        height: 25px;
        border: 1px solid #ccc;
    }

    #buttons>input:hover {
        background: #f8f8f8;
        cursor: pointer;
    }

    #noresult {
        width: 75%;
        margin: 50px auto;
        padding: 2px;
        border: 2px solid #ccc;
        text-align: center;
        background: #eee;
        display: none;
    }

    #resultTable,
    #singleItem {
        border-collapse: collapse;
        border: 2px solid #ccc;
        width: 75%;
        margin: 30px auto;
        padding: 2px;
    }

    thead tr td{
        text-align: center;
    }

    #resultTable,
    #singleItem,
    th,
    td,
    tr {
        border: 2px solid #ccc;
        
    }

    .fa-chevron-down {
        background: url('http://csci571.com/hw/hw6/images/arrow_down.png');
        height: 17px;
        background-size: contain;
        width: 27px;
        margin: 0px auto;
    }

    .fa-chevron-up {
        background: url('http://csci571.com/hw/hw6/images/arrow_up.png');
        height: 17px;
        background-size: contain;
        width: 27px;
        margin: 0px auto;
    }

    #itemdetails {
        display: none;
        text-align: center;
    }

    th {
        text-align: center;
    }

    #title {
        margin: 0px;
        text-align: center;
    }

    .miles {
        margin-left: 30px;
    }

    .here {
        margin-left: 20px;
    }

    #milesfrom {
        margin-left: 343px;
    }

    td img {
        display: block;
        margin: 0 auto;
        height: 100px;
        width: auto;
    }

    .sellerMsgBtn {
        margin: 10px auto;
        width: 600px;
        text-align: center;
    }

    .sellerMsgBtn p {
        margin: 0px;
        color: #ccc;
        padding-bottom: 3px;
    }

    /* .sellerMsgBtn i {
        color: #ccc;
        font-size: 2.5em;
    } */

    .similarItemsBtn {
        margin: 10px auto;
        width: 600px;
        text-align: center;
    }

    .similarItemsBtn p {
        margin: 0px;
        color: #ccc;
        padding-bottom: 3px;
    }

    /* .similarItemsBtn i {
        color: #ccc;
        font-size: 2.5em;
    } */

    #iframe {
        width: 80%;
        margin: 10px auto;
        height: auto;
        overflow: visible;
        display: none;
    }

    .similartable {
        width: 80%;
        margin: 10px auto;
        display: none;
        border: 2px solid #ccc;
        overflow-x: auto;
    }

    .similartable td div {
        width: 150px;
    }
    .nosimilaritems{
        width: 80%;
        margin: 1px auto;
    }

    .similartable tr td {
        border: none;
        padding-left: 20px;
        padding-right: 20px;
        padding-top: 10px;
    }
    </style>
    <script type="text/javascript">
    // show the result
    function callFindAPI() {
        var xmlhttpreq = new XMLHttpRequest();

        var keyword = document.getElementsByName("keywords")[0].value
        var categoryId = document.getElementById("categories").value

        var buyerPostalCode = ""

        var itemFilter = [];
        itemFilter[0] = {}
        itemFilter[1] = {}
        itemFilter[2] = {}
        itemFilter[3] = {}
        itemFilter[4] = {}
        itemFilter[0].name = 'HideDuplicateItems'
        itemFilter[0].value = true
        var count = 1;
        if (document.getElementsByName("ens")[0].checked) {
            itemFilter[count].name = 'MaxDistance'
            itemFilter[count].value = (document.getElementsByName("milesvalue")[0].value === "") ? 10 : document
                .getElementsByName("milesvalue")[0].value
            count++
            if (document.getElementsByName("milesfrom")[0].checked) {
                buyerPostalCode = document.getElementById("location").innerHTML
            } else {
                buyerPostalCode = document.getElementsByName("zipvalue")[0].value
            }
        }

        var LocalPickupOnly = document.getElementsByName("so")[0].checked
        var FreeShippingOnly = document.getElementsByName("so")[1].checked
        var new_condition = document.getElementsByName("c")[0].checked
        var used = document.getElementsByName("c")[1].checked
        var unspecified = document.getElementsByName("c")[2].checked
        if (FreeShippingOnly) {
            itemFilter[count].name = 'FreeShippingOnly'
            itemFilter[count].value = true
            count++
        }
        if (LocalPickupOnly) {
            itemFilter[count].name = 'LocalPickupOnly'
            itemFilter[count].value = true
            count++
        }
        var condition_count = 0
        itemFilter[count].value = []
        if (new_condition || used || unspecified) {
            itemFilter[count].name = 'Condition'
        }
        if (new_condition) {
            itemFilter[count].value[condition_count] = 'New'
            condition_count++
        }
        if (used) {
            itemFilter[count].value[condition_count] = 'Used'
            condition_count++
        }
        if (unspecified) {
            itemFilter[count].value[condition_count] = 'Unspecified'
            condition_count++
        }
        var post_data = "keywords=" + keyword
        post_data += "&find="
        post_data += (categoryId === "") ? "" : "&categoryId=" + categoryId
        post_data += (buyerPostalCode === "") ? "" : "&buyerPostalCode=" + buyerPostalCode

        for (var i = 0; i < itemFilter.length; i++) {
            if (!itemFilter[i].name) {
                break
            }
            if (itemFilter[i].name !== "Condition") {
                post_data += "&itemFilter(" + i + ")" + ".name=" + itemFilter[i].name
                post_data += "&itemFilter(" + i + ")" + ".value=" + itemFilter[i].value
            } else {
                post_data += "&itemFilter(" + i + ")" + ".name=" + itemFilter[i].name
                for (var j = 0; j < itemFilter[i].value.length; j++) {
                    post_data += "&itemFilter(" + i + ")" + ".value(" + j + ")=" + itemFilter[i].value[j]
                }
            }
        }


        xmlhttpreq.onreadystatechange = function() {
            if (this.readyState == 4 && this.status == 200) {
                createTable(JSON.parse(this.responseText))
                // console.log(JSON.parse(this.responseText))
            }
        };
        xmlhttpreq.open("POST", "index.php", true);
        xmlhttpreq.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
        xmlhttpreq.send(post_data)

    }

    function createTable(object) {
        document.getElementById('pageTwoOptions').style.display = 'none';
        document.getElementById
        var zipvalue = document.getElementsByName('zipvalue')[0].value
        var regexp = /(^\d{5}$)|(^\d{5}-\d{4}$)/;
        var tab = document.getElementById("resultTable")
        var noresult = document.getElementById("noresult")
        tab.innerHTML = ''
        noresult.innerHTML = ''
        document.getElementById("singleItem").innerHTML = ''
        if ((zipvalue !== '') && (!regexp.test(zipvalue))) {
            document.getElementById('noresult').style.display = 'block';
            noresult.innerHTML = "Zipcode is invalid"
        } else if (object.length == 0) {
            document.getElementById('noresult').style.display = 'block';
            noresult.innerHTML = "No Record has been found"
        } else {
            document.getElementById('noresult').style.display = 'none';
            var header = tab.createTHead();
            var row = header.insertRow(0);
            row.insertCell(0).innerHTML = "<b>Index</b>";
            row.insertCell(1).innerHTML = "<b>Photo</b>";
            row.insertCell(2).innerHTML = "<b>Name</b>";
            row.insertCell(3).innerHTML = "<b>Price</b>";
            row.insertCell(4).innerHTML = "<b>Zip code</b>";
            row.insertCell(5).innerHTML = "<b>Condition</b>";
            row.insertCell(6).innerHTML = "<b>Shipping Option</b>";
            var body = tab.createTBody();
            for (var i = 0; i < object.length; i++) {
                var tr = body.insertRow(i)
                tr.insertCell(0).innerHTML = i + 1
                if (object[i]['galleryURL'] == "N/A") {
                    tr.insertCell(1).innerHTML = 'No Photo to display.'
                } else {
                    tr.insertCell(1).innerHTML = '<img src=\'' + object[i]['galleryURL'] + '\'>'
                }
                tr.insertCell(2).innerHTML = '<a id= \"searchResultTitle\"  onclick= getSingleItem(' + object[i][
                    'itemId'
                ] + ')>' + object[i]['title'] + '</a>'
                tr.insertCell(3).innerHTML = "$" + object[i]['currPrice']
                tr.insertCell(4).innerHTML = object[i]['postalCode']
                tr.insertCell(5).innerHTML = object[i]['condition']
                if (object[i]['shippingCost'] == "Free") {
                    tr.insertCell(6).innerHTML = "Free Shipping"
                } else if (object[i]['shippingCost'] == "N/A") {
                    tr.insertCell(6).innerHTML = "N/A"
                } else {
                    tr.insertCell(6).innerHTML = "$" + object[i]['shippingCost']
                }
            }
        }
    }

    function getSingleItem(itemID) {
        var post_data = "itemId=" + itemID
        var XHR = new XMLHttpRequest()
        // console.log(itemID)
        XHR.onreadystatechange = function() {
            if (this.readyState == 4 && this.status == 200) {
                createSingleItemResult(JSON.parse(this.responseText))
            }
        }
        XHR.open("POST", "index.php", true);
        XHR.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
        XHR.send(post_data);
    }

    function createSingleItemResult(object) {
        document.getElementById('itemdetails').style.display = 'block';
        document.getElementById('pageTwoOptions').style.display = 'block';
        if (object["Ack"] === "Failure") {
            alert("Error: " + object['Error'] + "Please search again.")
        } else {
            document.getElementById("resultTable").innerHTML = ''
            document.getElementById("noresult").innerHTML = ''

            document.getElementById("singleItem").innerHTML = ''
            document.getElementById("similartable").innerHTML = ''

            var singleItem = document.getElementById("singleItem")
            var tr
            if (object["PictureURL"] !== null) {
                tr = singleItem.insertRow(-1)
                tr.insertCell(-1).innerHTML = "<b>Photo</b>"
                tr.insertCell(-1).innerHTML = '<img src=\'' + object['PictureURL'] + '\'>'
            }
            if (object["Title"] !== null) {
                tr = singleItem.insertRow(-1)
                tr.insertCell(-1).innerHTML = "<b>Title</b>"
                tr.insertCell(-1).innerHTML = object['Title']
            }
            if (object["Subtitle"] !== null) {
                tr = singleItem.insertRow(-1)
                tr.insertCell(-1).innerHTML = "<b>SubTitle</b>"
                tr.insertCell(-1).innerHTML = object['Subtitle']
            }
            if (object["Price"] !== null) {
                tr = singleItem.insertRow(-1)
                tr.insertCell(-1).innerHTML = "<b>Price</b>"
                tr.insertCell(-1).innerHTML = "$ " + object['Price']
            }
            if (object["Location"] !== null) {
                tr = singleItem.insertRow(-1)
                tr.insertCell(-1).innerHTML = "<b>Location</b>"
                tr.insertCell(-1).innerHTML = object['Location']

            }
            if (object["Seller"] !== null) {
                tr = singleItem.insertRow(-1)
                tr.insertCell(-1).innerHTML = "<b>Seller</b>"
                tr.insertCell(-1).innerHTML = object['Seller']
            }
            if (object["ReturnPolicy"] !== undefined && object["ReturnPolicy"] !== null) {
                tr = singleItem.insertRow(-1)
                tr.insertCell(-1).innerHTML = "<b>ReturnPolicy (US)</b>"
                tr.insertCell(-1).innerHTML = object['ReturnPolicy']
            }
            if (object["ItemSpecifics"].length !== 0) {
                for (var key in object['ItemSpecifics']) {
                    tr = singleItem.insertRow(-1)
                    tr.insertCell(-1).innerHTML = '<b>' + key + '</b>'
                    tr.insertCell(-1).innerHTML = object['ItemSpecifics'][key]
                }
            }

            if (object['Description'] !== "") {
                var elem = document.createElement('textarea');
                elem.innerHTML = object['Description'];
                var decoded = elem.value;
                document.getElementById('iframe').srcdoc = decoded;
                elem.remove()
            } else {
                document.getElementById('iframe').srcdoc =
                    "<html><head><style>p{text-align: center;}body{height: 50px;}</style></head><body><p>No Seller Message to display</p></body></html>"
            }
            if (object['SimilarItems'].length !== 0) {
                var tab = document.getElementById('similartable')
                tr = tab.insertRow(-1)
                object['SimilarItems'].forEach(function(elem) {
                    tr.insertCell(-1).innerHTML = "<div class = \"similaritem\"><img src =" + elem['imageURL'] +
                        "><br><a onclick= getSingleItem(" + elem['itemId'] + ")>" + elem['title'] +
                        "</a><br><p>" + elem['price'] + "</p><br></div>"
                })
            } else {
                var tab = document.getElementById('similartable')
                tr = tab.insertRow(-1).insertCell(-1).innerHTML = "No Similar Items to display."
            }

        }
    }


    function getLocation() {
        var submitButton = document.getElementById("submit")
        submitButton.disabled = true;
        var locationDiv = document.getElementById("location")
        if (locationDiv.innerHTML === "") {
            var XHR = new XMLHttpRequest();
            XHR.onreadystatechange = function() {
                if (this.readyState == 4 && this.status == 200) {
                    locationDiv.innerHTML = JSON.parse(this.responseText)["zip"]
                    submitButton.disabled = false;
                }
            }
            XHR.open("GET", "http://ip-api.com/json", true);
            XHR.send();
        } else {
            submitButton.disabled = false;
        }

    }

    function closeAllfromSingleItem() {
        if (document.querySelector(".sellerMsgBtn p").innerHTML === "Click to hide seller message") {
            toggleFunction()
        }
        if (document.querySelector(".similarItemsBtn p").innerHTML === "Click to hide similar items") {
            toggleFunction2()
        }


        // document.querySelector(".sellerMsgBtn i").classList.toggle('fa-chevron-down')
        // document.querySelector(".similarItemsBtn i").classList.toggle('fa-chevron-down')
        // document.querySelector(".iframe").style.display = 'none'
        // document.querySelector(".similartable").style.display = 'none'
    }

    function toggleFunction() {
        var contentOther = document.querySelector(".similartable")
        if (window.getComputedStyle(contentOther).display === 'block') {
            toggleFunction2()
        }
        var p = document.querySelector(".sellerMsgBtn p")
        if (p.innerHTML === "Click to show seller message") {
            p.innerHTML = "Click to hide seller message"
        } else {
            p.innerHTML = "Click to show seller message"
        }
        x = document.querySelector(".sellerMsgBtn div")
        // console.log("icon before")
        // console.log(x)
        x.classList.toggle('fa-chevron-up')
        // console.log("icon after")
        // console.log(x)
        var content = document.querySelector(".iframe")
        toggle(content)
    }

    function toggleFunction2() {
        var contentOther = document.querySelector(".iframe")
        if (window.getComputedStyle(contentOther).display === 'block') {
            toggleFunction()
        }
        var p = document.querySelector(".similarItemsBtn p")
        if (p.innerHTML === "Click to show similar items") {
            p.innerHTML = "Click to hide similar items"
        } else {
            p.innerHTML = "Click to show similar items"
        }
        x = document.querySelector(".similarItemsBtn div")
        // console.log("icon before")
        // console.log(x)
        x.classList.toggle('fa-chevron-up')
        // console.log("icon after")
        // console.log(x)
        var content = document.querySelector(".similartable")
        // console.log(content)
        toggle(content)
    }

    var show = function(elem) {
        elem.style.display = 'block';
    };

    var hide = function(elem) {
        elem.style.display = 'none';
    };

    var toggle = function(elem) {
        if (window.getComputedStyle(elem).display === 'none') {
            show(elem);
            return;
        }
        hide(elem);
    };
    </script>
</head>

<body onload=getLocation()>
    <!-- <b>Keyword </b><input type="text" id="keywords" name="keywords">
    <button type="submit" value="Submit" onClick="callFindAPI()">Search</button> -->
    <div id="location" hidden></div>

    <form id='form' onSubmit="callFindAPI();closeAllfromSingleItem();return false">
        <h1 id='title'>Product Search</h1>
        <hr>
        Keyword <input type='text' name='keywords' required>
        <br><br>
        Category
        <select id='categories'>
            <option value="">All Categories</option>
            <option value="550">Art</option>
            <option value="2984">Baby</option>
            <option value="267">Books</option>
            <option value="11450">Clothing Shoes & Accessories</option>
            <option value="58058">Computers/Tablets & Networking</option>
            <option value="26395">Health & Beauty</option>
            <option value="11233">Music</option>
            <option value="1249">Video Games & Consoles</option>
        </select>
        <br><br>
        Condition
        <input type='checkbox' name='c' value='New'> New
        <input type='checkbox' name='c' value='Used'> Used
        <input type='checkbox' name='c' value='Unspecified'> Unspecified
        <br><br>
        Shipping Options
        <input type='checkbox' name='so' value='so1'> Local Pickup
        <input type='checkbox' name='so' value='so2'> Free Shipping
        <br><br>

        <input type='checkbox' name='ens' value='ens' onclick="
                var miles = document.getElementsByName('milesvalue')[0]
                var hereRadio = document.getElementsByName('milesfrom')[0]
                var zipRadio = document.getElementsByName('milesfrom')[1]
                var zipValue = document.getElementsByName('zipvalue')[0]
                if(this.checked){
                    miles.disabled = false
                    hereRadio.disabled = false
                    zipRadio.disabled = false
                }
                else{
                    miles.disabled = true
                    hereRadio.disabled = true
                    zipRadio.disabled = true
                    zipValue.disabled = true
                    hereRadio.checked = true                  
                }"> Enable Nearby Search
        <input class='miles' type='text' name='milesvalue' placeholder="10" maxlength='5' size='4' pattern='\d+'
            oninvalid="setCustomValidity('Try to search with a number')" onchange="try{setCustomValidity('')}catch(e){}"
            disabled> miles from
        <input class='here' type="radio" name="milesfrom" value="here" onclick="
                document.getElementsByName('zipvalue')[0].disabled = !document.getElementsByName('milesfrom')[1].checked
                " disabled checked>Here
        <br>
        <div id='milesfrom'>
            <input type="radio" name="milesfrom" value="zipcode"
                onclick="document.getElementsByName('zipvalue')[0].disabled = !this.checked;" disabled>
            <input type='text' name='zipvalue' placeholder='zipcode' maxlength='5' minlength='5' size='8' disabled
                required>
        </div>
        <br><br>
        <div id='buttons'>
            <input type="submit" id="submit" value="Search">
            <input type="reset" id="reset" value="Clear" onClick="                
                var miles = document.getElementsByName('milesvalue')[0]
                var hereRadio = document.getElementsByName('milesfrom')[0]
                var zipRadio = document.getElementsByName('milesfrom')[1]
                var zipValue = document.getElementsByName('zipvalue')[0]
                miles.disabled = true
                hereRadio.disabled = true
                zipRadio.disabled = true
                zipValue.disabled = true
                hereRadio.checked = true
                document.getElementById('resultTable').innerHTML = '';
                document.getElementById('noresult').innerHTML = ''
                document.getElementById('singleItem').innerHTML = ''
                document.getElementById('similartable').innerHTML = ''
                document.getElementById('pageTwoOptions').style.display = 'none';
                closeAllfromSingleItem();
                document.getElementById('noresult').style.display = 'none';

            ">
        </div>
    </form>
    <table id="resultTable">
    </table>
    <div id="noresult"></div>
    <div id="pageTwoOptions" style="display:none">
        <h1 id="itemdetails">Item Details</h1>
        <table id="singleItem"></table>

        <div class="sellerMsgBtn">
            <p>Click to show seller message</p>
            <div onClick="toggleFunction(); var height = document.getElementById('iframe').contentWindow.document.body.scrollHeight+50;
                document.getElementById('iframe').style.height = height + 'px' " class='fa-chevron-down'></div>
        </div>
        <iframe id="iframe" class='iframe'></iframe>

        <div class="similarItemsBtn">
            <p>Click to show similar items</p>
            <div onClick="toggleFunction2()" class='fa-chevron-down'></div>
        </div>
        <table id='similartable' class='similartable'></table>
    </div>
</body>

</html>
<?php 
} ?>
