
<div class="d-flex  p-0 stepelt">
            <input type="hidden" id="category" value="<?php echo $iCategory->name; ?>" />
                                                        

            <div class="dispsrc rounded-2 shadow  text-center">
                <div class="row p-0">
                  <span class="archers">
                  <?php echo $iround->step; ?> (<?php echo $iround->name; ?>)
                  </span>


               </div>
            </div>
 <?php 
        $Coef= $iround->isTeam ? 2 : 1; 
        $CibleCount = count($iround->matches) *  $Coef;
        $MatchCible = min(array_map(fn($m) => $m->getMinTarget(),$iround->matches));
        $MatchSchedule = array_values($iround->matches)[0]->schedule->format('YmdHi');
        ?>
            
    <div class="Tile-<?php echo $iround->name; ?> Tile targettile zgg-2 position-relative opacity-75 border border-dark rounded-1 p-0 text-center"
         style="background-color: <?php echo $iCategory->color; ?>;">
        <input type="hidden" id="cibleCount" value="<?php echo $CibleCount; ?>" />
        <input type="hidden" id="matchCible" value="<?php echo $MatchCible; ?>" />
        <input type="hidden" id="matchSchedule" value="<?php echo $MatchSchedule; ?>"/>
        <input type="hidden" id="eventType" value="match"/>
        <input type="hidden" id="isTeam" value="<?php echo ($iround->isTeam)?1:0; ?>"/>
        <input type="hidden" id="matchPerTarget" value="<?php echo $iround->matchPerTarget; ?>"/>
        <input type="hidden" id="athPerTarget" value="<?php echo $iround->athPerTarget; ?>"/>
        <input type="hidden" id="category" value="<?php echo $iround->name; ?>"/>
        <input type="hidden" id="step" value="<?php echo $iround->stepNum; ?>"/>
        <input type="hidden" id="matchsNo" value="<?php echo implode(",",$iround->getMatchNo()); ?>"/>
        <input type="hidden" id="matchDuration" value="<?php echo $iround->duration; ?>"/>
        <input type="hidden" id="letters" value="<?php echo $iround->getLetters(); ?>" />
        
        <div class="position-absolute top-0 end-0 zgg-2 btRmTile" onclick="removeTile(this)">
            <span data-bs-toggle="tooltip" data-bs-placement="top" data-bs-delay="{&quot;show&quot;:500,&quot;hide&quot;:100}" data-bs-title="Supprimer">
                <i class="bi bi-trash-fill" aria-hidden="true"></i>
            </span>                                                           
        </div>           

         <div class="d-flex flex-column  contain p-0" style="font-size: x-small;">
             <div class="text-center">
                 <span> <?php echo $iround->step; ?><br> <?php echo $iround->name; ?></span>
             </div>

             <div class="d-flex flex-row justify-content-around text-center ">
                 <?php
                    //$isLeft = true;
                    $matchcount = 0;
                    $cOrder = 0;
                    foreach ($iround->matches as $imatch ) { 
                    //     $isFirst = ($imatch->cutPlace == 1 || $imatch->cutPlace == ($iround->stepNum *2) ) ? true : false;
                    //    $isLast = ($imatch->cutPlace == 4 ||  $imatch->cutPlace == (($iround->stepNum *2) -1)) ? true : false;
                    
                     if($iround->athPerTarget == 1) {
                        ?>
                    <?php foreach ($imatch->players as $iplayer ) { 
                            $matchcount++;?>
                        <div class="d-flex flex-column mt-1 mb-1">
                            <div class="d-flex flex-row vague justify-content-center"  style="">
                                <div class=" border border-dark rounded-1 ps-1 pe-1 player">
                                 <input type="hidden" id="pMatchNo" value="<?php echo $iplayer->matchNo; ?>" />
                                 <input type="hidden" id="pLetter" value="<?php echo $iplayer->letter; ?>" />
                                 <input type="hidden" id="cOrder" value="<?php echo $cOrder++; ?>" />
                                 <div class="d-flex flex-fill flex-column  align-items-center" >
                                    <span><?php echo $iplayer->cutPlace; ?></span>
                                    <div class="imgBlason visually-hidden p-0">
                                        <?php echo $iCategory->getAbrev(); ?> 
                                    </div>
                                    <div class="imgBlason visually-hidden d-flex flex-fill p-0 align-items-center">
                                        <img src="<?php echo URL; ?>/svg/<?php echo $iCategory->targetFace; ?>.svg" style="width:<?php echo $iCategory->targetSize/4; ?>px;" class="">
                                    </div>
                                    <div class="imgBlason visually-hidden Diam">
                                        ⌀<?php echo $iCategory->targetSize; ?> 
                                    </div>
                                </div>
                                 
                                 
                                    
                                   </div >
                            </div>
                        </div>
                        <?php 
                                if($matchcount!= count($imatch->players) ) {?>
                                <div class="d-flex flex-column m-0 vagueSep"  style="">
                               </div>
                        <?php } ?>
                    <?php } ?>

                    <?php } else { 
                        $matchcount++;
                        ?>
                        <div class="d-flex flex-column mt-1 mb-1">
                            <div class="d-flex flex-row vague justify-content-center"  style="width: 59px;">

                        <?php foreach ($imatch->players as $iplayer ) { ?>

                             <div class=" border border-dark rounded-1 ps-1 pe-1 player" >
                                 <input type="hidden" id="pMatchNo" value="<?php echo $iplayer->matchNo; ?>" />
                                 <input type="hidden" id="pLetter" value="<?php echo $iplayer->letter; ?>" />
                                 <input type="hidden" id="cOrder" value="<?php echo $cOrder++; ?>" />
                                 <div class="d-flex flex-fill flex-column  align-items-center" >
                                    <span><?php echo $iplayer->cutPlace; ?></span>
                                    <div class="imgBlason visually-hidden p-0">
                                        <?php echo $iCategory->getAbrev(); ?> 
                                    </div>
                                    <div class="imgBlason visually-hidden d-flex flex-fill p-0 align-items-center">
                                        <img src="<?php echo URL; ?>/svg/<?php echo $iCategory->targetFace; ?>.svg" style="width:<?php echo $iCategory->targetSize/4; ?>px;" class="">
                                    </div>
                                        <div class="imgBlason visually-hidden Diam">
                                        ⌀<?php echo $iCategory->targetSize; ?> 
                                    </div>
                                </div>
                            </div >
                        <?php } ?>
                            </div>
                        </div>
                            <?php 
                                if($matchcount!= count($iround->matches) ) {?>
                                <div class="d-flex flex-column m-0 vagueSep"  style="width: 6px;">
                               </div>
                            <?php } ?>
                        <?php } ?>
                    <?php } ?>
                 
             </div>

         </div>


    </div>   

</div>

