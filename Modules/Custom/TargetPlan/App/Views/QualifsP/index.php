
<style>
    <?php 
    foreach ($session->getStructColor() as $sId => $color) {
        ?>
         <?php echo '.bgstru'.$sId;?> { background-color: <?php echo $color;?>; }
    <?php
    }
    ?>
    
</style>

<h2><?php echo LANG['PLAN_FOR']; ?> <?php echo $session->tour->name ?></h2>
<div class="row">
    <div class="col-6 col-sm-3">
    <form >
        <div class="form-floating mb-2">
            <select class="form-control form-control-sm" name="sessId" id="DepartNum" placeholder="" onchange="this.form.submit()">
            <?php 
            foreach ($session->tour->sessions as $ses) { 
                   if($session->order == $ses->id ) {
                       echo '<option value="'.$ses->id.'" selected >'.$ses->name.'</option>';
                   } 
                   else {
                       echo '<option value="'.$ses->id.'">'.$ses->name.'</option>';
                   }
            }
            
            ?>    
            </select>
            <label for="DepartNum"><?php echo LANG['SESSION']; ?></label>
        </div>
        <div class="form-floating mb-2">
            <select class="form-control form-control-sm" name="sort" id="sortBy" placeholder="" onchange="this.form.submit()">
                <option value="0" <?php echo ($sortBy == 0 )?"selected":""; ?> ><?php echo LANG['TARGET_FACE']; ?></option>
                <option value="1" <?php echo ($sortBy == 1 )?"selected":""; ?> ><?php echo LANG['CATEGORIES']; ?></option>
            </select>
            <label for="sortBy"><?php echo LANG['GROUPBY']; ?></label>
        </div>
        
    </form>
    <br />
    <input type="hidden" value="<?php echo $sortBy; ?>" id="groupBy" />
    
    
    
    
    </div>
    <div class="col-9 text-center">
        <h1 >
            <?php echo $session->name; ?> - <?php echo $session->start; ?>
        </h1>

    </div>
</div>

<br/>






<div class="container-fluid p-2 m-3" >
    <div class="row">
        <div class="col-2 shadow p-2 border rounded-3 overflow-y-auto" style="height:90vh;">
            <div class="form-check form-switch text-start" style="font-size: small; font-weight: normal;">
                <input class="form-check-input" onclick="hideSwitch();" type="checkbox" role="switch" id="toggleArcher" checked="checked">
                <label class="form-check-label" ><?php echo LANG['SHOW_ARCHERS']; ?></label>
            </div>
            <div class="form-check form-switch text-start" style="font-size: small; font-weight: normal;">
                <input class="form-check-input" onclick="hideAffectedSwitch();" type="checkbox" role="switch" id="toggleAffected" checked="checked">
                <label class="form-check-label" ><?php echo LANG['SHOW_AFFECTED']; ?></label>
            </div>
            <div class="accordion" id="PickingList">
                <?php
                switch ($sortBy) {
                case 0:
                    include (VIEWS.$ControlerName.DS."PickingListByBlasons.php");
                    break;
                case 1:
                    include (VIEWS.$ControlerName.DS."PickingListByCat.php");
                    break;
                    }
                ?>
                
                
                
             
            </div>
        </div>

        <div class="col-10">

            <div id="recapBlason" class="d-flex flex-wrap align-items-center border border-2 p-1 border-primary rounded-3">
                <p class="placeholder-glow text-center">
                    <span class="placeholder w-75"></span>
                    <span class="placeholder w-25"></span>
                    <span class="placeholder w-50"></span>
                </p>
            </div>
            <div class="d-flex flex-sm-wrap align-content-start overflow-y-auto m-2" style="height:80vh;">
                <input type="hidden" id="departId" value="<?php echo $session->order; ?>" />
                <?php for($cible = 1; $cible <= $session->targets ; $cible++) { ?>
                
                    <div id="Cible-<?php echo $cible; ?>" class="d-flex flex-column p-0 shadow cibleNum">
                        <input type="hidden" id="cibleNum" value="<?php echo $cible; ?>" />

                    <div class="d-flex flex-column contain border border-2 p-1 border-primary rounded-3">
                        <div class=" text-center position-relative">
                            <span>Cible <?php echo $cible; ?></span>
                            <span class="btRm position-absolute top-0 end-0 badge border border-light rounded-circle bg-light p-0" 
                                onclick="removeCible(this)">
                                <i class="bi bi-x-circle" style="color: red;font-size: medium;"></i>
                            </span>
                        </div>
                            <div class="d-flex flex-row border border-dark rounded-3 text-center placeholder-glow">
                                <div class="flex-fill border border-dark rounded-start-3">
                                    <span class="placeholder w-50"></span>
                                </div>
                                <div class="flex-fill border border-dark rounded-end-3">
                                    <span class="placeholder w-50"></span>
                                </div>
                        </div>
                            <div class=" d-flex flex-column flex-fill " style="background-color: cornsilk;">
                            <p class="placeholder-glow text-center">
                                <span class="placeholder w-50"></span>
                                <span class="placeholder w-25"></span>
                                <span class="placeholder w-25"></span>
                                <span class="placeholder w-50"></span>
                                <span class="placeholder w-50"></span>
                                <span class="placeholder w-25"></span>
                                <span class="placeholder w-25"></span>
                                <span class="placeholder w-50"></span>
                            </p>
                        </div>
                    </div>

                        <div id="cb<?php echo $cible; ?>" class=" d-flex flex-column contain border border-1 p-1 border-primary rounded-3">
                            <p class="placeholder-glow text-center">
                                <span class="placeholder w-50"></span>
                                <span class="placeholder w-25"></span>
                                <span class="placeholder w-25"></span>
                                <span class="placeholder w-50"></span>
                                <span class="placeholder w-50"></span>
                                <span class="placeholder w-25"></span>
                                <span class="placeholder w-25"></span>
                                <span class="placeholder w-50"></span>
                            </p>
                    </div>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>

</div>

