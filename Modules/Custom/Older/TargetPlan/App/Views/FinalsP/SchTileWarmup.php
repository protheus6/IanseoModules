
<div class="d-flex  p-0 stepelt">
        <input type="hidden" id="category" value="<?php echo $iCategory->name; ?>" />        
        <div class="dispsrc rounded-2 shadow  text-center">
            <div class="row p-0">
              <span class="archers">
              <?php echo LANG['WARMUP']; ?> (<?php echo $iround->name; ?>)
              </span>


           </div>
        </div>
        
    <div class="Tile-<?php echo $warmup->category ?> Tile targettile zgg-2 position-relative opacity-75 border border-dark rounded-1 p-0 text-center" 
        style="background-color: #FFFFFF;" >
        
        <input type="hidden" id="cibleCount" value="<?php echo $warmup->getTargetCount(); ?>" />
        <input type="hidden" id="matchCible" value="<?php echo $warmup->getTarget();?>" />
        <input type="hidden" id="matchSchedule" value="<?php echo $warmup->schedule->format('YmdHi'); ?>"/>
        <input type="hidden" id="originalSchedule" value="<?php echo $warmup->schedule->format('YmdHi'); ?>"/>
        <input type="hidden" id="eventType" value="warmup"/>
        <input type="hidden" id="isTeam" value="<?php echo ($warmup->isTeam)?1:0; ?>"/>
        <input type="hidden" id="matchPerTarget" value=""/>
        <input type="hidden" id="athPerTarget" value=""/>
        <input type="hidden" id="category" value="<?php echo $iCategory->name; ?>"/>
        <input type="hidden" id="step" value=""/>
        <input type="hidden" id="matchsNo" value=""/>
        <input type="hidden" id="matchDuration" value="<?php echo $warmup->duration; ?>"/>
        <input type="hidden" id="letters" value="AB" />
        
        <div class="position-absolute top-0 end-0 zgg-2 btRmTile" onclick="removeTile(this)">
            <span data-bs-toggle="tooltip" data-bs-placement="top" data-bs-delay="{&quot;show&quot;:500,&quot;hide&quot;:100}" data-bs-title="Supprimer">
                <i class="bi bi-trash-fill" aria-hidden="true"></i>
            </span>                                                           
        </div>    


         <div class="d-flex flex-column  contain p-0" style="font-size: x-small;">
             <div class="text-center">
                 <span> <?php echo LANG['WARMUP']; ?><br> <?php echo $iCategory->name; ?></span>
             </div>

             <div class="d-flex flex-row justify-content-around text-center ">
                 <?php
                    
                    for ($i=1; $i<=$warmup->getTargetCount(); $i++) {?>
                        
                        <div class="d-flex flex-column mt-1 mb-1">
                            <div class="d-flex flex-row vague justify-content-center"  style="">
                                <div class=" border border-dark rounded-1 ps-1 pe-1">
                                    <span></span>
                                </div >
                            </div>
                        </div>
                        <?php if($i!= $warmup->getTargetCount() ) {?>
                                <div class="d-flex flex-column m-0 vagueSep"  style="">
                               </div>
                        <?php } ?>
                        
                    <?php } ?>
                 
             </div>

         </div>

        
   </div>
</div>











