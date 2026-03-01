
<?php foreach ($session->blasonCount() as $item) { ?>

    <div class="border border-1 p-1 border-secondary rounded-1 m-1" onmouseover="haloBlason(this);" onmouseout="haloBlasonOut(this);" id="tgl-<?php echo $item->id; ?>">
            <img src="<?php echo URL; ?>/svg/<?php echo $item->img ?>" style="width:<?php echo $item->taille; ?>px;">
        <?php echo $item->nom; ?> :  <span class="fw-bold fs-4"><?php echo $item->count; ?></span>
    </div>
    
 <?php } ?>


                 
   