



<tr class="border border-1 border-dark schTr " id="targetSch-<?php echo $sch->start->format('YmdHi'); ?>" >
    <th class=" sticky-cell p-0" style="max-width: 215px;">
        <div class="d-flex flex-row flex-fill" style="max-width: 215px;" >
            <div class=" p-0 m-0 " style="min-width: 25px; max-width: 25px;" >

               <div class=" p-0 m-0 schLabel  " >
                    <span class="btRmTr" onclick="removeTr(this)">
                        <i class="bi bi-dash-square-fill" aria-hidden="true"></i>
                    </span> 
                </div>
                <div class=" p-0 m-0  schEdit" >
                   <span class="btSaveSc" onclick="saveSc(this)"
                       data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="Valider"
                       data-bs-delay="{&quot;show&quot;:500,&quot;hide&quot;:100}">
                            <i class="bi bi-floppy2-fill" aria-hidden="true"></i>
                    </span>
                </div>


                <div class=" p-0 m-0 schLabel" >
                    <span class="btAddTr" onclick="addTr(this)">
                        <i class="bi bi-plus-square-fill" aria-hidden="true"></i>
                    </span>
                </div> 
               <div class=" p-0 m-0 schEdit" >
                  <span class="btCancelSc" onclick="cancelSc(this)"
                         data-bs-toggle="tooltip" data-bs-placement="right" data-bs-title="Annuler"
                         data-bs-delay="{&quot;show&quot;:500,&quot;hide&quot;:100}">
                            <i class="bi bi-x-square" aria-hidden="true"></i>
                    </span>
                </div> 


            </div>
            <div class="flex-column flex-fill p-0 scheader schLabel " ondblclick="editSc(this)" style="min-width: 100px;" >
                <input type="hidden" id="sch" value="<?php echo $sch->start->format('YmdHi'); ?>" />
                <input type="hidden" id="duration" value="<?php echo $sch->duration; ?>" />
                <input type="hidden" id="matchPerTarget" value="<?php echo $sch->matchPerTarget?>" />
                
                <div class="flex-fill justify-content-center text-center p-0  schTr" style="font-size: small;">
                    <span id="scDate"><?php echo $sch->start->format('Y-m-d'); ?></span>
                </div>
                 <div class="flex-fill text-center  schTr" style="font-size: small;">
                    
                    <span id="scStart"><?php echo $sch->start->format('H:i'); ?></span> - <span id="scEnd"><?php echo $sch->getEnd()->format('H:i'); ?></span>


                </div>
            </div>
            <div class="flex-column flex-fill  p-0  scheader align-items-center schEdit shcHide" style="min-width: 170px;"  >
                <div class="position-relative text-center p-0" style="font-size: small;">
                    <div class="col  "> 
                        <input type="datetime-local" class=" form-control form-control-sm" id="fDate" value="2024-10-12T10:30">
                        <div class="input-group input-group-sm">
                            <span class="input-group-text" id="ig-Dur">Durée</span>
                            <input type="number" min="5" step="5" id="fDuration" value="2" class="form-control" aria-label="Duration in minutes" aria-describedby="ig-Dur">
                        </div>

                        <div class="form-check form-switch text-start">
                            <input class="form-check-input" type="checkbox" role="switch" id="isABCD">
                            <label class="form-check-label" for="isABCD">ABCD</label>
                        </div>
                    </div>

                </div>
            </div>

            <div class="d-flex flex-column  p-0" style="width:20px;">
                <div class="d-flex text-center align-items-center" style="min-heigght:200px !important;">
                    <div class="d-flex flex-column flex-fill">
                        <div class="d-flex flex-column flex-fill planVague align-items-center">
                            <div class="flex-fill">
                                A
                            </div >
                            <div class="flex-fill">
                                B
                            </div>
                        </div>

                            <div class=" <?php echo ($sch->matchPerTarget == 1 )?"cdDisabled":""; ?> ABCD d-flex flex-column flex-fill border-top border-primary  planVague align-items-center">
                            <div class="flex-fill">
                               C
                           </div>
                           <div class="flex-fill">
                               D
                           </div> 

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </th>


<?php for($cible = 1; $cible <= $tournament->getMaxTarget() ; $cible++) { ?>
    <td class=" border border-top-0 border-bottom-0 border-4 p-0 align-top" >
    <input type="hidden" id="cible" value="<?php echo $cible; ?>">
    
    <div class="d-flex flex-column flex-fill">
            <div id="cible-<?php echo $cible; ?>-AB" class="dragula-container d-flex flex-column flex-fill planVague">
                <input type="hidden" id="vague" value="AB">
            </div>

            <div id="cible-<?php echo $cible; ?>-CD" class="<?php echo ($sch->matchPerTarget == 1 )?"cdDisabled":""; ?> ABCD dragula-container d-flex flex-column flex-fill  border-top border-primary planVague">
                 <input type="hidden" id="vague" value="CD">
            </div>
    </div>

    </td>
 <?php } ?>                                    

</tr>