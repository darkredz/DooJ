var section = '';
var func = '';
var resources;
var apiKey = "PUT YOUR API KEY";

$(document).ready(function() {

//	Alpaca.logLevel = Alpaca.DEBUG;
    $.ajax({
        type: 'POST',
        beforeSend: function (request)
        {
            request.setRequestHeader("Authority", apiKey);
        },
        url: '/api/api-form/list-api/'+ section +'/' + func,
        success: onApiListReturn
    });
});

function onApiListReturn(json){
    console.log(json);
    resources = json;
    var rlist = '';
    for(var p in json){
        rlist += '<li><a href="javascript:;" onclick="selectResource(event)">' + p + '</a></li>';
    }
    $('#resource-list > .dropdown-menu').html(rlist);
}

function selectResource(event){
    section = $(event.target).text();
    console.log(section);
    var rlist = '';
    for(var p in resources[section]){
        rlist += '<li><a href="javascript:;" onclick="selectAction(event)">' + resources[section][p] + '</a></li>';
    }
    $('#resource-list > button').html(section + ' <span class="caret"></span>');
    $('#action-list > .dropdown-menu').html(rlist);
}

function selectAction(event){
    func = $(event.target).text();
    console.log(section, func);
    $('#action-list > button').html(func + ' <span class="caret"></span>');

    $.ajax({
        type: 'POST',
        beforeSend: function (request)
        {
            request.setRequestHeader("Authority", apiKey);
        },
        url: '/api/api-form/schema/'+ section +'/' + func,
        success: onApiTestSelected
    });
}

function onApiTestSelected(json){
    $('#form-bottom-buttons').show()
    console.log(json);

    var options = {
        "renderForm": true,
        "form": {
            "attributes":{
                "method":"POST", "action": '/api/'+ section +'/' + func
            },
//            "buttons":{
//                "submit":{},
//                "reset": {}
//            }
        }
    };

    var postRenderCallback = function(control) {
    };

    $("#form").html('');
    $('#result-cont').html('');

    $("#form").alpaca({
        "schema": json.schema,
        "options": options,
        "postRender": postRenderCallback,
        "ui": "bootstrap",
//        "view": "VIEW_WEB_EDIT_LIST"
    });

    $("#form").unbind('submit')
    $("#form").submit(function( event ) {
        event.preventDefault();
        $.ajax({
            type: $("#form > form").attr('method'),
            beforeSend: function (request)
            {
                request.setRequestHeader("Authority", apiKey);
            },
            url: $("#form > form").attr('action'),
            data: $("#form > form").serialize(),
            processData: false,
            success: function(msg) {
                console.log(msg);

                if(msg===true){
                    $('#result-cont').text("true");
                }
                else if( typeof msg == 'string'){
                    $('#result-cont').text(msg);
                }
                else{
                    var node = new PrettyJSON.view.Node({
                        el: $('#result-cont'),
                        data:msg
                    });
                }
            },
            error: function(msg){
                console.log(msg.responseText)
                msg = JSON.parse(msg.responseText);
                console.log(msg);
                if(msg===false){
                    $('#result-cont').text("false");
                }
                else if( typeof msg == 'string'){
                    $('#result-cont').text(msg);
                }
                else{
                    var node = new PrettyJSON.view.Node({
                        el: $('#result-cont'),
                        data:msg
                    });
                }
            }
        });
    });
}