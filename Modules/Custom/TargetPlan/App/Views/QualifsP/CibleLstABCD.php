<div class="d-flex flex-fill flex-column align-items-center <?php echo (isset($vague->blason))?"tgl-".$vague->blason->img->id:""; ?>">
    <div class="d-flex flex-fill align-items-center">
        <img src="<?php echo URL; ?>/svg/<?php echo $vague->blason->img->img; ?>" style="width:<?php echo $vague->blason->img->taille;?>px;" class="<?php echo ($vague->overlay)?"opacity-25":""; ?>" />
    </div>
    <div class="Diam">
        <?php  echo ($vague->overlay)?"-":$vague->blason->img->label; ?>
    </div>
</div>