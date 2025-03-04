<?php
//ini_set('display_errors',1);
//ini_set('display_startup_errors',1);
//error_reporting(-1);

?>

   <!-- <p onclick="ToJson()"> Test</p> -->
    <div class="position-absolute bottom-0 end-0 addCible" onclick="addTarget()">
        <span class="btAddCible" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="Add Target" data-bs-delay='{"show":500,"hide":100}'>
            <i class="bi bi-plus-square-fill" style="font-size: 1.5rem;"></i>
        </span>
    </div>
<div class="text-center">
<h4 > <?php echo $tournament->code; ?> - <?php echo $tournament->name; ?></h4>
<h6 > <?php echo $tournament->dtFrom->format('Y-m-d'); ?>  --  <?php echo $tournament->dtTo->format('Y-m-d'); ?> </h6>
</div>
<div class="container-fluid">
    <div class="row">
        <!-- <div class="invisible" id="defaultValues">
                <input type="hidden" id="defaultWarmupI" value="<?php echo $tournament->defaultWarmupI; ?>" />
                <input type="hidden" id="defaultWarmupT" value="<?php echo $tournament->defaultWarmupT; ?>" />
                <input type="hidden" id="defaultMatchI" value="<?php echo $tournament->defaultMatchI; ?>" />
                <input type="hidden" id="defaultMatchT" value="<?php echo $tournament->defaultMatchT; ?>" />
                                                        
            </div>
            -->
        <div class="col-2">
              <div class="shadow p-2 border rounded-3" style="min-width: 175px; max-width: 175px;">
                        <div class="accordion " id="PickingList">
                             <div class="accordion-item">
                                <input type="hidden" name="tCode" id="tCode" value="<?php echo $tournament->code; ?>" />
                                <input type="hidden" name="tId" id="tId" value="<?php echo $tournament->id; ?>" />
                                <input type="hidden" name="category" value="config" />
                                
                                <h2 class="accordion-header toggleTile" id="config">
                                <button class="accordion-button p-1 collapsed" type="button" 
                                        data-bs-toggle="collapse" data-bs-target="#flush-config" 
                                        aria-expanded="false" aria-controls="flush-config">
                                   <?php echo LANG['CONFIG']; ?>
                                </button>
                                </h2>
                                <div id="flush-config" class="accordion-collapse collapse PickingList" data-bs-parent="#PickingList">
                                   <div class="accordion-body p-0">
                                       <div class=" d-flex flex-column flex-fill text-center ddsrc" id="finItem-config">
                                            <div id="" class="">
                                                <div class="d-flex  p-0 " id="defaultValues">
                                                 <input type="hidden" id="category" value="config" />
                                                 <div id="defaultValues">
                                                    
                                                     <div class="card m-2" >
                                                        <div class="card-body">
                                                          <h6 class="card-title"><?php echo LANG['TEAM']; ?></h6>
                                                            <div class="form-floating">
                                                                <input id="defaultWarmupT" type="number" min="5" step="1" aria-label="First name" class="form-control form-control-sm" value="<?php echo $tournament->defaultWarmupT; ?>">
                                                                <label for="defaultWarmupT"><?php echo LANG['WARMUP']; ?></label>
                                                            </div>
                                                            <div class="form-floating">
                                                                <input id="defaultMatchT" type="number" min="5" step="1" aria-label="Last name" class="form-control form-control-sm" value="<?php echo $tournament->defaultMatchT; ?>">
                                                                <label for="defaultMatchT"><?php echo LANG['MATCH']; ?></label>
                                                            </div>
                                                            
                                                        </div>
                                                      </div>
                                                     
                                                     <div class="card m-2">
                                                        <div class="card-body">
                                                          <h6 class="card-title"><?php echo LANG['INDIV']; ?></h6>
                                                            <div class="form-floating">
                                                                <input id="defaultWarmupI" type="number" min="5" step="1" aria-label="Last name" class="form-control form-control-sm" value="<?php echo $tournament->defaultWarmupI; ?>">
                                                                <label for="defaultWarmupI"><?php echo LANG['WARMUP']; ?></label>
                                                            </div>
                                                            <div class="form-floating">
                                                                <input id="defaultMatchI" type="number" min="5" step="1" aria-label="Last name" class="form-control form-control-sm" value="<?php echo $tournament->defaultMatchI; ?>">
                                                                <label for="defaultMatchI"><?php echo LANG['MATCH']; ?></label>
                                                            </div>
                                                        </div>
                                                      </div>
                                                     
                                                </div>
                                                 
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            
                            
                            
                            <?php foreach ($tournament->categories as $iCategory) { ?>
                            
                            <div class="accordion-item">
                                <input type="hidden" name="category" value=" <?php echo $iCategory->name; ?>" />
                                <h2 class="accordion-header toggleTile" id="<?php echo $iCategory->name; ?>">
                                <button class="accordion-button p-1 collapsed" type="button" 
                                        data-bs-toggle="collapse" data-bs-target="#flush-<?php echo $iCategory->name; ?>" 
                                        aria-expanded="false" aria-controls="flush-<?php echo $iCategory->name; ?>">
                                    <?php echo $iCategory->name; ?>
                                </button>
                                </h2>
                                <div id="flush-<?php echo $iCategory->name; ?>" class="accordion-collapse collapse PickingList" data-bs-parent="#PickingList">
                                   <div class="accordion-body">
                                       <div class=" d-flex flex-column flex-fill text-center ddsrc" id="finItem-<?php echo $iCategory->name; ?>">
                                            <div id="FinalContent" class="dragula-container">
                                                <?php foreach ($iCategory->rounds as $iround) { ?>
                                                   <?php 
                                                    include (VIEWS.$ControlerName.DS."SchTile.php");
                                                    ?>
                                                <?php } ?>
                                                        
                                                <?php foreach ($iCategory->warmups as $warmup) { ?>
                                               
                                                <?php 
                                                    include (VIEWS.$ControlerName.DS."SchTileWarmup.php");
                                                    ?>
                                                
                                                
                                                <?php } ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php } ?>
                            <?php foreach (array_filter($tournament->planSchedules, fn($s) => $s->isClear ) as $sch) { ?>
                            
                           
                            <?php } ?>
                            
                           
                        </div>
                        </div>
        </div>
        <div class="col table-responsive sticky-table sticky-headers sticky-ltr-cells" style="max-height: 90vh;">
             <table class="table plan " id="Plan">
                            <thead>
                                <tr class="sticky-top opacity-75 cibleHeader">
                                    <th class=" sticky-cell p-2" style="max-width: 215px;"  >
                                        <button type="button" class="btn btn-outline-primary btn-sm" id="btSave" onclick="openSave();"><i class="bi bi-floppy2-fill" aria-hidden="true"></i></button>
                                        
                                        <div class="form-check form-switch text-start" style="font-size: small; font-weight: normal;">
                                            <input class="form-check-input" type="checkbox" role="switch" id="isUpdate" checked>
                                            <label class="form-check-label" for="isUpdate"><?php echo LANG['UPDATE_TIME']; ?></label>
                                        </div>
                                        <div class="form-check form-switch text-start" style="font-size: small; font-weight: normal;">
                                            <input class="form-check-input" onclick="hideSwitch();" type="checkbox" role="switch" id="toggleBlason" >
                                            <label class="form-check-label" for="isUpdate"><?php echo LANG['SHOW_FACES']; ?></label>
                                        </div>

                                    </th>
                                    
                                    
                                    <?php for($cible = 1; $cible <= $tournament->getMaxTarget() ; $cible++) { ?>
                                        <th class="position-relative">
                                        <div class="text-center">
                                            <input type="hidden" id="num" value="<?php echo $cible; ?>" />
                                        Cible <br> <?php echo $cible; ?>
                                        </div>
                                        </th>
                                    <?php } ?>
                                </tr>
                                </thead>

                                

                                    <tr class="border border-1 border-dark schTr emptytr " id="targetSch-0">
                                       
                                        <th class="sticky-cell p-0" style="max-width: 215px;">
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
                                                    <input type="hidden" id="sch" value="empty" />
                                                    <input type="hidden" id="duration" value="empty" />
                                                    <input type="hidden" id="matchPerTarget" value="empty" />

                                                    <div class="flex-fill justify-content-center text-center p-0  schTr" style="font-size: small;">
                                                        <span id="scDate"></span>
                                                    </div>
                                                     <div class="flex-fill text-center  schTr" style="font-size: small;">

                                                        <span id="scStart"></span> - <span id="scEnd"></span>


                                                    </div>
                                                </div>
                                                <div class="flex-column flex-fill  p-0  scheader align-items-center schEdit shcHide" style="min-width: 170px;"  >
                                                    <div class="position-relative text-center p-0" style="font-size: small;">
                                                        <div class="col  "> 
                                                            <input type="datetime-local" class=" form-control form-control-sm" id="fDate" value="2024-10-12T10:30">
                                                            <div class="input-group input-group-sm">
                                                                <span class="input-group-text" id="ig-Dur"><?php echo LANG['DURATION']; ?></span>
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

                                                                <div class=" cdDisabled ABCD d-flex flex-column flex-fill border-top border-primary  planVague align-items-center">
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
                                                    </div>
                                                    <div id="cible-<?php echo $cible; ?>-CD" class="cdDisabled dragula-container d-flex flex-column flex-fill  border-top border-primary planVague">
                                                    </div>
                                            </div>
                                        </td>
                                         <?php } ?>                                    
                                    </tr>
                             <?php
                             $scid=0;
                             foreach ($tournament->planSchedules as $sch) { 
                                 $scid++;
                                 $dStart =clone($sch->start);
                                 $sEnd = clone($sch->start);
                                 $sEnd->add(new \DateInterval('PT' . $sch->duration . 'M'));
                                 ?>
                                
                                <?php 
                                    include (VIEWS.$ControlerName.DS."SchLine.php");
                                ?>
                                
                            <?php } ?>
                        </table>
        </div>
    </div>
</div>
      
    <textarea class="form-control" id="dumpJson" rows="50" style="display:none;"></textarea>





