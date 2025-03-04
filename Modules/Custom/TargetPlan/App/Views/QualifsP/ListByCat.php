
                        <?php foreach ($session->participants as $item) {  
                            $bgcol = (isset($item))? "bgstru" . $item->structId:"";
                            $idcol = (isset($item)) ? $item->structId : "";     
                            
                            ?>

                         <div class="flex-fill p-1 <?php echo ($item->target > 0)?"affected":""; ?>" id="<?php echo $idcol; ?>" onmouseover="haloStruct(this);" onmouseout="haloStructOut(this);">
                                <input type="hidden" id="archerId" value="<?php echo $item->id; ?>" />
                                <input type="hidden" id="cibleNum" value="<?php echo $item->target; ?>" />
                                <input type="hidden" id="blasonType" value="<?php echo (isset($item->blason))?"acc-".$item->blason->img->h."-".$item->blason->img->v:""; ?>" />
                                
                             <div class="<?php echo $bgcol;?> disptrg " id="<?php echo $idcol; ?>" onmouseover="haloStruct(this);" onmouseout="haloStructOut(this);">
                                <span class="archers"> <?php echo $item->classe ; ?> - <?php echo $item->nom ; ?> </span>
                                 </div>
                             <div class="position-relative dispsrc crounded-2 shadow  text-center  <?php echo $bgcol;?>">
                                 <?php if($item->target) { ?>
                                <span class="position-absolute top-0 start-0 translate-middle badge border border-light rounded-circle bg-light p-0">
                                     <i class="bi bi-check-circle" style="color: green;font-size: medium;"></i>
                                 </span>
                                 <?php } ?>  
                                     <div class="row p-0">
                                         <span class="archers">
                                         <?php echo $item->getNomCourt(); ?> (<?php echo $item->getCible(); ?>)
                                         </span>
                                     </div>
                                     <div class="row p-0">
                                         <span class="archers">
                                                 <?php echo $item->structName; ?>
                                         </span>
                                     </div>
                              </div>
                         </div>
                         <?php } ?>
                    








                 
   