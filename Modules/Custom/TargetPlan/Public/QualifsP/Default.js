
 document.addEventListener('DOMContentLoaded', e => {
    blasonRecap(); 
    GetBlason($('#PickingList'));
     $('[id^=Cible-]').each(function () {
         var elt = this;
         GetCible(elt);
     });
     loadDragula();
    

 }, false);

 function haloBlason(elt)
 {
    var cls = '.' + elt.id;
    $(cls).addClass("halo");
 }
 function haloBlasonOut(elt)
 {
    var cls = '.' + elt.id;
    $(cls).removeClass("halo");
 }
 
 function haloCat(elt)
 {
    var cls = '.' + elt.id;
    $(cls).addClass("halo");
 }
 function haloCatOut(elt)
 {
    var cls = '.' + elt.id;
    $(cls).removeClass("halo");
 }



 function haloStruct(elt)
 {
    var cls = '.bgstru' + elt.id;
    $(cls).addClass("halobgstru");
 }
 function haloStructOut(elt)
 {
    var cls = '.bgstru' + elt.id;
    $(cls).removeClass("halobgstru");
 }
 
 
 

 function hideSwitch(){
     if ($('#toggleArcher').prop('checked')) {
         $('.nameArcher').removeClass('visually-hidden');
     }else {
         $('.nameArcher').addClass('visually-hidden');
     }
 }
 
 function hideAffectedSwitch()
 {
         if ($('#toggleAffected').prop('checked')) {
         $('#PickingList .affected').removeClass('visually-hidden');
     }else {
         $('#PickingList .affected').addClass('visually-hidden');
     } 
 }

 function blasonRecap(){
     $.get('QualifsP/BlasonRecap',
         {
             sessId: $('#departId').val()
         },
         function (data, status) {
             $('#recapBlason').html(data);
         }
     );
 }

 function GetBlason(item) {
     $(item).find('[id^=blsItem-]').each(function () {
         var elt = this;
         $.get('QualifsP/PickingList',
             {
                sessId: $('#departId').val(),
                tfId: $(elt).find('#blasonNum').val(),
                cat: $(elt).find('#category').val(),
                sort: $('#groupBy').val()
             },
             function (data, status) {
                $(elt).find('#blasonContent').html(data);
                var eltCount = $(elt).find('#blasonContent').children().length;
                var eltAffCount = $(elt).find('#blasonContent .affected').length;
                
                $(elt).parent().closest('.accordion-item').find('#memberCount').text(eltCount);
                $(elt).parent().closest('.accordion-item').find('#memberAffectedCount').text(eltAffCount);
                hideAffectedSwitch();
             }
         );
         
       
 
     });
     
 };

 function GetCible(item) {
     if ($(item).find('#cibleNum').val() !== undefined) {
         $.get('QualifsP/Cible',
             {
                sessId: $('#departId').val(),
                cibleNum: $(item).find('#cibleNum').val()
             },
             function (data, status) {
                 $(item).html(data);

             }
         );
     }

 };
 function MoveArcher(archer, source, target) {
 
     $.get('QualifsP/MoveArcher',
         {
            sessId: $('#departId').val(),
            cNum: $(target).parent().find('#cibleNum').val(),
            cLetter: $(target).parent().find('#cibleLetter').val(),
            archerId: $(archer).find('#archerId').val()
         },
         function (data, status) {
            GetCible($(target).parent().closest('[id^=Cible-]'));
            GetCible($(source).parent().closest('[id^=Cible-]'));
            GetCible($('#Cible-' + $(archer).find('#cibleNum').val()));
            GetBlason($('#PickingList'));
         }
     );
 };
 
 function removeCible(item)
 {
     var cible = $(item).parent().closest('[id^=Cible-]');
     
     var cibleArchers = $(cible).find('[id^=cb]');
     
     var ddCont = $(cibleArchers).find('.ddtrg');
     
     $.get('QualifsP/ClearCible',
         {
            sessId: $('#departId').val(),
            cibleNum: $(cible).find('#cibleNum').val()         },
         function (data, status) {
            GetCible(cible);
            GetBlason($('#PickingList'));
         }
        );
     
 }

function loadDragula(){
    var drake = dragula({
        isContainer: function (el) {
            return el.classList.contains('dragula-container');
        },
        accepts: function (el, target, source, sibling) {
            var srcls = $(el).find('#blasonType').val();
            var rtn = false;
            if (srcls === undefined) {
                rtn =  true;
            }
            else {
                if ($(target).attr('class').indexOf('acc-') === -1) {
                    rtn =  true;

                } else {
                    if (target.classList.contains(srcls)) {
                        rtn = true;
                    }
                }
            }
            return rtn;
        },
        removeOnSpill: true

    });

    drake.on("drop", function (el, target, source, sibling) {
        
        MoveArcher(el, source, target);
    });

    drake.on("cancel", function (el, container, source) {
       
    });
    drake.on("remove", function (el, container, source) {
        
        MoveArcher(el, source, null);
    });
}

 