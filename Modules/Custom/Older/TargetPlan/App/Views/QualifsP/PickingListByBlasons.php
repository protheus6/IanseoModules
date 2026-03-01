    
            <?php foreach ($session->blasonCount() as $blason) { ?>
                
                    <div class="accordion-item" onmouseover="haloBlason(this);" onmouseout="haloBlasonOut(this);" id="tgl-<?php echo $blason->id; ?>">
                        <h2 class="accordion-header togggleblason"  >
                            <button class="accordion-button p-1 collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#col<?php echo $blason->id; ?>" aria-expanded="false" aria-controls="col<?php echo $blason->id; ?>">
                                <img class="shadow me-1" src="<?php echo URL; ?>/svg/<?php echo $blason->img; ?>" style="height:25px" />
                                <?php echo $blason->nom; ?>
                                <span>&nbsp;(<span id="memberAffectedCount" >-</span>/<span id="memberCount" >-</span>)</span>
                            </button>
                        </h2>
                        <div id="<?php echo "col".$blason->id; ?>" class="accordion-collapse collapse " data-bs-parent="#PickingList">
                            <div class="accordion-body">
                                
                                <div class=" d-flex flex-column flex-fill text-center ddsrc" id="<?php echo "blsItem-".$blason->id; ?>">
                                    <input type="hidden" id="blasonNum" value="<?php echo $blason->id; ?>" />
                                    <input type="hidden" id="blasonType" value="<?php echo "acc-".$blason->h."-".$blason->v ?>" />
                                    
                                    <div id="blasonContent" class=" dragula-container">
                                        <p class="placeholder-glow text-center">
                                            <span class="placeholder w-50 m-1"></span>
                                            <span class="placeholder w-25 m-1"></span>
                                            <span class="placeholder w-25 m-1"></span>
                                            <span class="placeholder w-50 m-1"></span>
                                            <span class="placeholder w-50 m-1"></span>
                                        </p>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>
              <?php  } ?>  
