

document.addEventListener('DOMContentLoaded', e => {
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
    var pModal = new bootstrap.Modal(document.getElementById('ModalPrimary')); 
    
    $('.addCible').appendTo($( "tr.cibleHeader > th" ).last());




    $('#PickingList').find('.stepelt').each(function () {
            var tSch = '#targetSch-' + $(this).find('#matchSchedule').val();
            var tLetters = $(this).find('#letters').val(); 
            var tCible = '#cible-' + $(this).find('#matchCible').val() + "-"+ tLetters;
            var targetElt = $('#Plan').find(tSch).find(tCible);


            $(this).appendTo(targetElt);
        });

      loadDragula();

      $(".toggleTile").hover(function () {
        var cls = '.Tile-' + this.id;
        $(cls).toggleClass("halo");
        });
    
    document.getElementById('btSave').addEventListener('click', function () { 
        console.log("start opensave");

        $('#ModalPrimary').find('#modalTitle').text('Save Changes');
        $('#ModalPrimary').find('.modal-body').text('are you sure to save all modifications ?');
        $('#ModalPrimary').find('#confirm').show();
        pModal.show(); 

        console.log("end opensave");
        
    });    
    
    $('#ModalPrimary').find('#confirm').click(function(){
            pModal.hide(); 
          ToJson();
        });
        
    
            
            
            
            
        });
    
    function hideSwitch(){
        if ($('#toggleBlason').prop('checked')) {
            $('.imgBlason').removeClass('visually-hidden');
        }else {
            $('.imgBlason').addClass('visually-hidden');
        }
    }
    function removeTile(elt) {
            var eltSrc = $(elt).parent().closest('.stepelt');
            var fiCat = '#finItem-' + $(eltSrc).find('#category').val();
            var targetElt = $(fiCat).find('#FinalContent');
            eltSrc.appendTo(targetElt);
            updateEvent(eltSrc,null);
    } 
            
    function addTr(elt) {
        var $newTr = $('.emptytr').clone();
        var $TrElt = $(elt).closest('tr');
        $newTr.insertAfter($TrElt);
        $newTr.removeClass("emptytr" );
        
        
        var sch = $TrElt.find('#sch').val();
        if(sch === undefined ) return;
        var mEndCur =  moment(sch,"YYYYMMDDHHmm").add($TrElt.find('#duration').val(), 'm');
        $newTr.find('#sch').val(mEndCur.format("YYYYMMDDHHmm"));
        $newTr.find('#duration').val($TrElt.find('#duration').val());
        $newTr.find('#matchPerTarget').val($TrElt.find('#matchPerTarget').val());
        updateSchFromHidden($newTr);
        if($("#isUpdate").prop( "checked")){
            updateNext($newTr);
        }
      
         
    }
    
    function removeTr(elt) {
        var $TrElt = $(elt).closest('tr');
        $($TrElt).find('.stepelt').each(function () {
            
            var fiCat = '#finItem-' + $(this).find('#category').val();
            var targetElt = $(fiCat).find('#FinalContent');
            $(this).appendTo(targetElt);
            updateEvent(this,null);
            });
        $($TrElt).remove();
        var $firstTr = $(".plan").find('[id^=targetSch-]').not('.emptytr').first();
        if($("#isUpdate").prop( "checked")){
            updateNext($firstTr);
        }
        
        
    }
        
    function addTarget(){
        var $lHeader = $('tr.cibleHeader > th').last();
        var cibleNum = $lHeader.find('#num').val();
        cibleNum++;
        var nHeader = '<th class="position-relative"><div class="text-center"><input type="hidden" id="num" value="'+cibleNum+'" />Cible <br>'+cibleNum+' </div></th>';
        
        
        $('tr.cibleHeader').append(nHeader);
        $('.addCible').appendTo($( "tr.cibleHeader > th" ).last());
         var nTd = '<td class="dragula-container border border-top-0 border-bottom-0 border-4 p-0" id="cible-'+cibleNum+'">'
                +'<input type="hidden" id="cible" value="'+cibleNum+'" /></td>';
        $(".plan").find('[id^=targetSch-]').append(nTd);
    }
    
    function editSc(elt){
        var $TrElt = $(elt).closest('tr');
        var cDateTime = moment($TrElt.find('#sch').val(), "YYYYMMDDHHmm").format("YYYY-MM-DDTHH:mm");
        var cDuration = $TrElt.find('#duration').val();
        var cMatchPerTarget = $TrElt.find('#matchPerTarget').val();
        
        console.log('abcd-' + cMatchPerTarget);
        
        $TrElt.find('#isABCD' ).prop( 'checked', (cMatchPerTarget === "1") ? false:true );
        
        $TrElt.find('#fDate').val(cDateTime);
        $TrElt.find('#fDuration').val(cDuration);
        
        $TrElt.find('.schLabel').addClass("schHide");
        
        $TrElt.find('.schEdit').addClass("schShow");
        $TrElt.find('.schEdit').removeClass("schHide");
        
    }
    
    function cancelSc(elt){
        var $TrElt = $(elt).closest('tr');
        
        $TrElt.find('.schLabel').removeClass("schHide");
        
        $TrElt.find('.schEdit').addClass("schHide");
        $TrElt.find('.schEdit').removeClass("schShow");
        
        
    }  
    function saveSc(elt){
        var $TrElt = $(elt).closest('tr');
        if(!checkPrevious($TrElt)) return;
        
        var inDateTime = $TrElt.find('#fDate').val();
        var inDuration = $TrElt.find('#fDuration').val();
        var inMatchPerTarget = ($TrElt.find('#isABCD').prop('checked'))?2:1;
        
        $TrElt.find('#sch').val(moment(inDateTime).format("YYYYMMDDHHmm"));
        $TrElt.find('#duration').val(inDuration);
        $TrElt.find('#matchPerTarget').val(inMatchPerTarget);
        
        
        updateSchFromHidden($TrElt);
        updateTilesFromSh(elt);
        if($('#isUpdate').prop( 'checked')){
            updateNext($TrElt);
        }
        
              
        cancelSc(elt);
    }
    
    function updateTilesFromSh(elt){
         $(elt).find('.stepelt').each(function () {
                updateEvent(this);
            });
    }
    
    function updateNext(elt){
        var mEndCur =  moment($(elt).find('#sch').val(),"YYYYMMDDHHmm").add($(elt).find('#duration').val(), 'm');
        updateTilesFromSh(elt);
        
        var $TrElt = $(elt).next();
        var sch = $TrElt.find('#sch').val();
        if(sch === undefined ) return;

        $TrElt.find('#sch').val(mEndCur.format("YYYYMMDDHHmm"));
        updateSchFromHidden($TrElt);
        if($("#isUpdate").prop( "checked")){
            updateNext($TrElt);
        }
    }
    
    function checkPrevious(elt){
        
        var $TrElt = $(elt).prev().not('.emptytr');
       
        var sch = $TrElt.find('#sch').val();
       
        if(sch === undefined ) return true;
        
        var mEndPrev = moment(sch, "YYYYMMDDHHmm").add($TrElt.find('#duration').val(), 'm');
        var mEndCur =  moment($(elt).find('#fDate').val());
        var duration = moment.duration(mEndCur.diff(mEndPrev)).asSeconds();
       
        if(duration !== 0) return false; 
        return true;
    }
    
    function updateSchFromHidden(elt){
        
        var m = moment($(elt).find('#sch').val(), "YYYYMMDDHHmm");
        var hDuration = $(elt).find('#duration').val();
        var matchPerTarget = $(elt).find('#matchPerTarget').val();
       if(matchPerTarget === "1" ){
         $(elt).find('.ABCD').addClass('cdDisabled');
       }
       else {
          $(elt).find('.ABCD').removeClass('cdDisabled'); 
       }
        
        $(elt).find('#scDate').text(m.format("YYYY-MM-DD"));
        $(elt).find('#scStart').text(m.format("HH:mm"));
        $(elt).find('#scEnd').text(m.add(hDuration, 'm').format("HH:mm"));
        
        
    }
    
    
    function updateEvent(elt){ 
        
        var tr = $(elt).parent().closest('tr');
        var td = $(elt).parent().closest('td');
        var vague =  $(elt).parent().find('#vague').val();
        var cibleNum = $(td).find('#cible').val();
        var sch = tr.find('#sch').val();
        var duration = tr.find('#duration').val();
        var matchPerTarget = tr.find('#matchPerTarget').val();
        
         if(duration === undefined ) duration = 0;
        if(cibleNum === undefined ||sch === undefined ||duration === undefined ){
           cibleNum = "";
           sch = "";
           
           var isTeam = $(elt).find('#isTeam').val();
           var evType = $(elt).find('#eventType').val();
           if(evType === 'warmup' && isTeam ) duration = $('#defaultValues').find('#defaultWarmupT').val();
           if(evType === 'warmup' && !isTeam ) duration = $('#defaultValues').find('#defaultWarmupI').val();
           if(evType === 'match' && isTeam ) duration = $('#defaultValues').find('#defaultMatchT').val();
           if(evType === 'match' && !isTeam ) duration = $('#defaultValues').find('#defaultMatchI').val();
        }
        $(elt).find('#matchCible').val(cibleNum);
        $(elt).find('#matchSchedule').val(sch);
        $(elt).find('#matchDuration').val(duration); 
        $(elt).find('#matchPerTarget').val(matchPerTarget);
        $(elt).find('#letters').val(vague);
         console.log("update: "+cibleNum+";"+sch+";"+duration+";"+vague);
    } 
    
    function updateEventDrop(elt, target){
        
        var cibleNum = $(target).closest('td').find('#cible').val();
        var vague =  $(target).find('#vague').val();
        var tr = $(target).parent().closest('tr');
        var sch = tr.find('#sch').val();
        var duration = tr.find('#duration').val();
        var matchPerTarget = tr.find('#matchPerTarget').val();
        
        if(duration === undefined ) duration = 0;
        if(cibleNum === undefined ||sch === undefined ||duration === undefined ){
           cibleNum = "";
           sch = "";
           
           
           var isTeam = $(elt).find('#isTeam').val();
           var evType = $(elt).find('#eventType').val();
           if(evType === 'warmup' && isTeam ) duration = $('#defaultValues').find('#defaultWarmupT').val();
           if(evType === 'warmup' && !isTeam ) duration = $('#defaultValues').find('#defaultWarmupI').val();
           if(evType === 'match' && isTeam ) duration = $('#defaultValues').find('#defaultMatchT').val();
           if(evType === 'match' && !isTeam ) duration = $('#defaultValues').find('#defaultMatchI').val();
        }
        $(elt).find('#matchCible').val(cibleNum);
        $(elt).find('#matchSchedule').val(sch);
        $(elt).find('#matchDuration').val(duration);
        $(elt).find('#matchPerTarget').val(matchPerTarget);
        $(elt).find('#letters').val(vague);
        
        console.log("drop: "+cibleNum+";"+sch+";"+duration+";"+vague);
    }

function loadDragula(){
    var drake = dragula({
    isContainer: function (el) {
    return el.classList.contains('dragula-container');
    },
    accepts: function (el, target, source, sibling) {
        if (target.matches('.dragula-container') && target.children.length > 2) {
            return false;
        }
        return true;
    },
    revertOnSpill: true, 
    removeOnSpill: false

    });

    drake.on("drop", function (el, target, source, sibling) {
        updateEventDrop(el,target);
    });
        
    drake.on("remove", function (el, container, source) {
        updateEventDrop(el);
    });
    
    }
   
function openSave ()
{
    
}

function ToJson(){
    console.log("start tojson");

    var rootObj = new Object();
    rootObj.tId = $('#tId').val();
    rootObj.tCode = $('#tCode').val();
    rootObj.warmups = [];
    rootObj.events = [];
    $('.stepelt').each(function () {

            var eType = $(this).find('#eventType').val();
            if(eType === 'warmup'){
               var warmup = new Object();
               warmup.category =$(this).find('#category').val();
               warmup.schedule = $(this).find('#matchSchedule').val();
               warmup.originaSchedule = $(this).find('#originalSchedule').val();
               warmup.cible = $(this).find('#matchCible').val();
               warmup.cibleCount = $(this).find('#cibleCount').val();
               warmup.letters = $(this).find('#letters').val();
               warmup.isTeam = $(this).find('#isTeam').val();
               warmup.duration = $(this).find('#matchDuration').val();
               rootObj.warmups.push(warmup);
            }
            else {
               var evt = new Object();
               evt.category =$(this).find('#category').val();
               evt.schedule = $(this).find('#matchSchedule').val();
               evt.cible = $(this).find('#matchCible').val();
               evt.CibleCount = $(this).find('#cibleCount').val();
               evt.step = $(this).find('#step').val();
               evt.isTeam = $(this).find('#isTeam').val();
               evt.matchPerTarget = $(this).find('#matchPerTarget').val();
               evt.athPerTarget = $(this).find('#athPerTarget').val();
               evt.matchsNo = $(this).find('#matchsNo').val();
               evt.letters = $(this).find('#letters').val();
               evt.duration = $(this).find('#matchDuration').val();
               evt.players = [];
               $(this).find('.player').each(function () {
                    var ply = new Object();
                    ply.matchNo = $(this).find('#pMatchNo').val();
                    ply.letter = $(this).find('#pLetter').val();
                    ply.order = $(this).find('#cOrder').val();
                    evt.players.push(ply);
               });
               rootObj.events.push(evt);
            }


        });

    var jsonString= JSON.stringify(rootObj);
    $('#dumpJson').html(jsonString);
     var values = $(this).serialize();
    $.ajax({
        type: "POST",
        url: "FinalsP/JsonSave",
        data: {'events': jsonString },
        success: function(data){
            var pModal = new bootstrap.Modal(document.getElementById('ModalPrimary'));
            $('#ModalPrimary').find('#modalTitle').text('Save Successful');
            $('#ModalPrimary').find('.modal-body').html(data);
            $('#ModalPrimary').find('#confirm').hide();
            pModal.show();
            
        },
        error: function(errMsg) {
            var pModal = new bootstrap.Modal(document.getElementById('ModalPrimary'));
            $('#ModalPrimary').find('#modalTitle').text('Save Failed');
            $('#ModalPrimary').find('.modal-body').html(errMsg);
            $('#ModalPrimary').find('#confirm').hide();
            pModal.show();
        }
    });
    console.log("end ToJson");


}
   