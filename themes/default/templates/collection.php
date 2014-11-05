<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="<?php echo $self->context->dictionary->language ?>">
    <?php include 'head.php' ?>
    <body>
        
        <!-- Header -->
        <?php include 'header.php' ?>
        
        <div class="row fullWidth resto-title">
            <div class="large-12 columns">
                <h1><a href="http://jjrom.github.io/resto/"><?php echo $self->context->dictionary->translate('_headerTitle'); ?></a></h1>
                <p><?php echo $self->context->dictionary->translate('_headerDescription'); ?></p>
            </div>
        </div>
        <div class="collections">
            <div class="row fullWidth resto-collection" id="_<?php echo $self->name;?>"> 
                <div class="large-12 columns left">
                    <h1>
                        <a class="fa fa-search" href="<?php echo $self->context->baseUrl . 'api/collections/' . $self->name . '/search.html?lang=' . $self->context->dictionary->language; ?>">  <?php echo $self->osDescription[$self->context->dictionary->language]['ShortName']; ?></a><br/>
                    </h1>
                    <p><?php echo $self->osDescription[$self->context->dictionary->language]['Description']; ?></p>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <?php include 'footer.php' ?>
        
        <script type="text/javascript">
        $(document).ready(function() {
            R.language = '<?php echo $self->context->dictionary->language; ?>';
            R.translation = <?php echo json_encode($self->context->dictionary->getTranslation()) ?>;
            R.restoUrl = '<?php echo $self->context->baseUrl ?>';
            R.ssoServices = <?php echo json_encode($self->context->config['ssoServices']) ?>;
            R.userProfile = <?php echo json_encode(!isset($_SESSION['profile']) ? array('userid' => -1) : array_merge($_SESSION['profile'], array('rights' => isset($_SESSION['rights']) ? $_SESSION['rights'] : array()))) ?>;
            R.init();
        });
    </script>
        
    </body>
    
</html>
