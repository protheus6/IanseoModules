            <?php foreach ($session->listByCategory() as $cat) { ?>
                
                    <div class="accordion-item" onmouseover="haloCat(this);" onmouseout="haloCatOut(this);" id="tcat-<?php echo $cat->name; ?>">
                        <h2 class="accordion-header togggleblason"  >
                            <button class="accordion-button p-1 collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#col<?php echo $cat->name; ?>" aria-expanded="false" aria-controls="col<?php echo $cat->name; ?>">
                                <img class="shadow me-1" src="<?php echo URL; ?>/svg/<?php echo $cat->img->img; ?>" style="height:25px" />
                                <?php echo $cat->name; ?>
                                <span>&nbsp;(<span id="memberAffectedCount" >-</span>/<span id="memberCount" >-</span>)</span>
                            </button>
                        </h2>
                        <div id="<?php echo "col".$cat->name; ?>" class="accordion-collapse collapse " data-bs-parent="#PickingList">
                            <div class="accordion-body">
                                
                                <div class=" d-flex flex-column flex-fill text-center ddsrc" id="<?php echo "blsItem-".$cat->name; ?>">
                                    <input type="hidden" id="category" value="<?php echo $cat->name; ?>" />
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