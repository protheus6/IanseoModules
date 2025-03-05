
<?php
    $warnClass = "border-primary";
    switch ($cible->warnLevel) {
    case 0:
        $warnClass = "primary";
        break;
    case 1:
        $warnClass = "success";
        break;
    case 2:
        $warnClass = "warning";
        break;
    case 3:
        $warnClass = "danger";
        break;
}

    



?>

<input type="hidden" id="cibleNum" value="<?php echo $cible->num; ?>" />
<div class="d-flex flex-column contain border border-3 p-1 <?php echo "border-".$warnClass; ?> rounded-3 position-relative">
    <span class="btRm position-absolute bottom-0 start-50 translate-middle-x badge rounded-3 rounded-bottom-0 <?php echo "bg-".$warnClass; ?>">
    <?php echo LANG['WARN_'.$cible->warnLevel]; ?>
  </span>
    <div class=" text-center position-relative">
        <span>Cible <?php echo $cible->num; ?></span> 
        <span class="btRm position-absolute top-0 end-0  badge border border-light rounded-circle bg-light p-0" 
              onclick="removeCible(this)">
            <i class="bi bi-x-circle" ></i>
        </span>
    </div>
    <div class="d-flex flex-row border border-dark rounded-3 text-center">
        <?php 
        if(count($cible->vagues) == 4){
        ?>
        <div class="flex-fill border border-dark rounded-start-3">
            <span>
                <?php echo $cible->vagues[1]->label; ?>
                /
                <?php echo $cible->vagues[3]->label; ?>
            </span>
        </div>
        <div class="flex-fill border border-dark rounded-end-3">
            <span>
                <?php echo $cible->vagues[2]->label; ?>
                /
                <?php echo $cible->vagues[4]->label; ?>
            </span>
        </div>
        <?php 
        }
        else{
        ?>
        <div class="flex-fill border border-dark rounded-3 ">
            <span>
               <?php  echo implode('/', array_map(fn($m) => $m->label,$cible->vagues)); ?>
               
            </span>
        </div>
        
         <?php 
        }
        ?>
    </div>
    <div id="Content-Cible" class=" d-flex flex-column flex-fill " style="background-color: cornsilk;">
        <div class="d-flex flex-row flex-fill align-items-center text-center">
            
            <?php 
                if(count(array_filter($cible->vagues, function($v) {return isset($v->blason);} )) > 0){
                    
                    if($cible->getMaxNbArchers() == 4){
                      $vague = $cible->getVagueFromNb(4);
                    ?>
                    <div class="cibleACBD d-flex flex-column flex-fill align-items-center text-center">
                        <?php include "CibleAC.php"; ?>
                    </div> 
                    <?php    
                    }
                    else 
                    {
                      foreach($cible->GetVaguesOrdered() as $vaguesOrder)
                      {
                          if(count(array_filter($vaguesOrder, fn($f) => isset($f->blason))) ==0)
                          {
                            ?>
                            <div class="cibleAC d-flex flex-column flex-fill align-items-center text-center">
                            </div>
                            <?php
                          }
                          else if(max(
                                            array_map(fn($m) => $m->blason->img->getNbArcher(),
                                                        array_filter($vaguesOrder, fn($f) => isset($f->blason)))) == 2 )
                          {
                            $vague = current(array_filter($vaguesOrder, fn($f) => isset($f->blason)&& $f->blason->img->getNbArcher()==2 ));
                            ?>
                            <div class="cibleAC d-flex flex-column flex-fill align-items-center text-center">
                                <?php  include "CibleAC.php"; ?>
                            </div>
                            <?php 
                              
                          }
                          else 
                          {
                              
                            if(max(array_map(fn($m) => $m->blason->img->v,array_filter($vaguesOrder, fn($f) => isset($f->blason)))) == 2){
                                ?>
                                <div class="cibleAC d-flex flex-column flex-fill align-items-center text-center">
                                <?php
                                foreach($vaguesOrder as $vague){
                                    include "CibleAC.php";
                                }
                                ?>
                                </div>
                                <?php
                            }else {
                                foreach($vaguesOrder as $vague){
                                   ?>
                                    <div class="cibleA d-flex flex-fill flex-column align-items-center <?php echo (isset($vague->blason))?"tgl-".$vague->blason->img->id:""; ?> <?php echo (isset($vague->participant))?"tcat-".$vague->participant->getCategory():""; ?>">
                                            <div class="border">
                                                <img src="<?php echo URL; ?>/svg/<?php echo $vague->blason->img->img; ?>" style="width:<?php echo $vague->blason->img->taille;?>px;" class="<?php echo ($vague->overlay)?"opacity-25":""; ?>" />
                                            </div>
                                            <div class="Diam">
                                                 <?php  echo ($vague->overlay)?"-":$vague->blason->img->label; ?>
                                            </div>
                                        </div>
                                    <?php
                                }  
                            }
                        }
                    }   
                }
            }
            ?>
        </div>
    </div>
</div>

<div id="cb<?php echo $cible->num; ?>" class=" d-flex nameArcher flex-row contain border border-2 p-1 <?php echo "border-".$warnClass; ?> rounded-3 justify-content-center">
<?php 
    
        foreach($cible->GetVaguesOrdered() as $vaguesOrder)
        {
            foreach ($vaguesOrder as $vague)
            {
                $bgcol = (isset($vague->participant))? "bgstru" . $vague->participant->structId:"";
                $idcol = (isset($vague->participant)) ? $vague->participant->structId : "";
                ?>
                <div class="d-flex flex-column text-center <?php echo (isset($vague->blason))?"tgl-".$vague->blason->img->id:""; ?> <?php echo (isset($vague->participant))?"tcat-".$vague->participant->getCategory():""; ?>">
                    <input type="hidden" id="cibleNum" value="<?php echo $vague->target; ?>" />
                    <input type="hidden" id="cibleLetter" value="<?php echo $vague->order; ?>" />

                    <div class="flex border p-1 fw-bold"><?php echo $vague->label; ?></div>
                    <div class="dragula-container d-flex flex-column flex-fill text-center border ddtrg  <?php echo (isset($vague->blason))?"acc-".$vague->blason->img->h."-".$vague->blason->img->v:""; ?>" style="min-height:50px;">
                       
                        <?php 
                        if(isset($vague->participant))
                        {
                        ?>
                             <div class="flex-fill p-1" id="archer-container">
                                <input type="hidden" id="blasonType" value="<?php echo (isset($vague->blason))?"acc-".$vague->blason->img->h."-".$vague->blason->img->v:""; ?>" />
                                <input type="hidden" id="archerId" value="<?php echo $vague->participant->id; ?>" />
                                <input type="hidden" id="cibleNum" value="<?php echo $vague->participant->target; ?>" />
                                
                                <div class="<?php echo $bgcol;?> disptrg " id="<?php echo $idcol; ?>" onmouseover="haloStruct(this);" onmouseout="haloStructOut(this);">
                                    <span class="archers"><?php echo $vague->participant->getCategory()." - ".$vague->participant->getNomCourt(); ?></span>
                                </div>
                            </div>
                        <?php } ?>
                     </div>
                </div>
    <?php
            }
        }
?>
</div>

