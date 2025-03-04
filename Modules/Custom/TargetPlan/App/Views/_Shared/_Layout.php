
<!DOCTYPE html>
<html lang="fr">
    <head>
            <meta charset="utf-8" />
            <meta name="viewport" content="width=device-width, initial-scale=1.0" />
             <title><?=APP_TITTLE?></title>

            <link rel="stylesheet" href="<?php echo URL; ?>/lib/bootstrap/dist/css/bootstrap.css" />
            <link rel="stylesheet" href="<?php echo URL; ?>/lib/bs-icons/font/bootstrap-icons.min.css" />
            <link rel="stylesheet" href="<?php echo URL; ?>/lib/dragula/dist/dragula.min.css" />
            <link rel="stylesheet" href="<?php echo URL; ?>/lib/jquery-stickyTable/jquery.stickytable.min.css"  />
             <?php
            
            //echo  PUBLICDIR . $ControleName.DS.'Default.css';
            if (file_exists(PUBLICDIR . $ControlerName.DS.'Default.css')) {
               echo '<link rel="stylesheet" href="'.URL.'/'.URL_PUBLIC_FOLDER.'/'.$ControlerName."/Default.css".'"/>';
            }
            ?>

            
            <script src="<?php echo URL; ?>/lib/jquery/dist/jquery.js"></script>
            <script src="<?php echo URL; ?>/lib/bootstrap/dist/js/bootstrap.bundle.js"></script>
            <script src="<?php echo URL; ?>/lib/dragula/dist/dragula.min.js"></script>
            <script src="<?php echo URL; ?>/lib/moment/moment.min.js"></script>
            <script src="<?php echo URL; ?>/lib/jquery-stickyTable/jquery.stickytable.min.js"></script>

            <?php
            
           //   echo  PUBLICDIR . $ControleName.DS.'Default.js';
            if (file_exists(PUBLICDIR . $ControlerName.DS.'Default.js')) {
               echo '<script src="'.URL.'/'.URL_PUBLIC_FOLDER.'/'.$ControlerName."/Default.js".'"></script>';
            }
            ?>


    </head>
        <body>

            <header class="IgnoreToPrint">
     
            </header>
            
            <div class="container-fluid ms-0">
                <a href="<?php echo IANSEOROOT; ?>Main.php" class="btn btn-secondary m-2" role="button"> <i class="bi bi-arrow-return-left"></i></a>
                <div class="container-fluid">
                <main role="main" class="pb-3">
                
                <?php echo $ViewContent; ?>
                    
            </main>
                </div>
            </div>
            
            <div class="modal fade" id="ModalPrimary" data-bs-backdrop="static" aria-hidden="true" aria-labelledby="Modal" tabindex="-1" >
                <div class="modal-dialog modal-dialog-centered">
                  <div class="modal-content">
                    <div class="modal-header">
                      <h1 class="modal-title fs-5" id="modalTitle">Modal title</h1>
                      <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                      modal Body 
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?php echo LANG['CLOSE']; ?></button>
                        <button type="button" class="btn btn-primary" id="confirm" ><?php echo LANG['CONFIRM']; ?></button>
                    </div>
                  </div>
                </div>
            </div>
           
            <footer class="border-top footer text-muted IgnoreToPrint">
                <div class="container">
                    
                </div>
            </footer>
    </body>
</html>




