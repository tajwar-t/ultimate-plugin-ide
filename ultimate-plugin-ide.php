<?php
/*
Plugin Name: Ultimate Plugin IDE
Description: Full drag-and-drop WordPress Plugin IDE with live editing, create, rename, delete files/folders.
Version: 5.0
Author: Tajwar
*/

if(!defined('ABSPATH')) exit;

class Ultimate_Plugin_IDE {

    public function __construct() {
        add_action('admin_menu',[$this,'admin_menu']);
        add_action('admin_enqueue_scripts',[$this,'enqueue_assets']);
        add_action('admin_post_create_plugin',[$this,'create_plugin']);
        add_action('admin_post_save_plugin_file',[$this,'save_file']);

        // AJAX handlers
        add_action('wp_ajax_upide_get_structure',[$this,'ajax_get_structure']);
        add_action('wp_ajax_upide_get_file',[$this,'ajax_get_file']);
        add_action('wp_ajax_upide_save_file',[$this,'ajax_save_file']);
        add_action('wp_ajax_upide_create_file',[$this,'ajax_create_file']);
        add_action('wp_ajax_upide_create_folder',[$this,'ajax_create_folder']);
        add_action('wp_ajax_upide_rename_item',[$this,'ajax_rename_item']);
        add_action('wp_ajax_upide_delete_item',[$this,'ajax_delete_item']);
        add_action('wp_ajax_upide_activate',[$this,'ajax_activate']);
        add_action('wp_ajax_upide_deactivate',[$this,'ajax_deactivate']);
    }

    // Admin menu
    public function admin_menu(){
        add_menu_page('Ultimate Plugin IDE','Plugin IDE','manage_options','ultimate-plugin-ide',[$this,'admin_page'],'dashicons-editor-code',65);
    }

    // Enqueue JS/CSS
    public function enqueue_assets($hook){
        if($hook!=='toplevel_page_ultimate-plugin-ide') return;
        wp_enqueue_script('jquery');
        wp_enqueue_script('jquery-ui-sortable');
        wp_enqueue_style('codemirror-css','https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.15/codemirror.min.css');
        wp_enqueue_script('codemirror-js','https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.15/codemirror.min.js',[],null,true);
        wp_enqueue_script('codemirror-php','https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.15/mode/php/php.min.js',['codemirror-js'],null,true);
        wp_enqueue_script('codemirror-css-mode','https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.15/mode/css/css.min.js',['codemirror-js'],null,true);
        wp_enqueue_script('codemirror-js-mode','https://cdnjs.cloudflare.com/ajax/libs/codemirror/5.65.15/mode/javascript/javascript.min.js',['codemirror-js'],null,true);

        // Inline JS for IDE functionality
        wp_add_inline_script('jquery', $this->get_inline_js());
        wp_add_inline_style('codemirror-css', $this->get_inline_css());
    }

    // Admin page
    public function admin_page(){
        ?>
        <div class="wrap">
            <h1>Ultimate Plugin IDE</h1>

            <h2>Create New Plugin</h2>
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <input type="hidden" name="action" value="create_plugin">
                <?php wp_nonce_field('upide_create','upide_create_nonce'); ?>
                <table class="form-table">
                    <tr><th>Name</th><td><input type="text" name="plugin_name" required></td></tr>
                    <tr><th>Slug</th><td><input type="text" name="plugin_slug" required></td></tr>
                    <tr><th>Description</th><td><input type="text" name="plugin_description"></td></tr>
                    <tr><th>Author</th><td><input type="text" name="plugin_author" value="Tajwar"></td></tr>
                    <tr><th>Version</th><td><input type="text" name="plugin_version" value="1.0"></td></tr>
                    <tr><th>Starter Code</th><td><textarea name="starter_code" rows="6" cols="50">// PHP code</textarea></td></tr>
                    <tr><th>Activate</th><td><input type="checkbox" name="activate" value="1"></td></tr>
                </table>
                <?php submit_button('Create Plugin'); ?>
            </form>

            <h2>Installed Plugins</h2>
            <div id="plugin-list">
                <?php
                $plugins = scandir(WP_PLUGIN_DIR);
                foreach($plugins as $plugin){
                    if(in_array($plugin,['.','..'])) continue;
                    $status = is_plugin_active($plugin.'/'.$plugin.'.php') ? 'Active' : 'Inactive';
                    echo '<div class="plugin-item"><strong>'.$plugin.'</strong> ('.$status.') ';
                    echo '<button class="open-plugin button" data-plugin="'.$plugin.'">Open</button> ';
                    echo '<button class="activate-plugin button" data-plugin="'.$plugin.'">Activate</button> ';
                    echo '<button class="deactivate-plugin button" data-plugin="'.$plugin.'">Deactivate</button></div>';
                }
                ?>
            </div>

            <div id="plugin-editor" style="display:none; margin-top:20px;">
                <h2>Edit Plugin: <span id="editing-plugin"></span></h2>
                <div id="file-tree"></div>
                <textarea id="file-editor"></textarea>
                <button id="save-file" class="button button-primary" style="margin-top:10px;">Save File</button>
            </div>
        </div>
        <?php
    }

    // Create plugin
    public function create_plugin(){
        if(!current_user_can('manage_options')) wp_die('Permission denied');
        if(!isset($_POST['upide_create_nonce'])||!wp_verify_nonce($_POST['upide_create_nonce'],'upide_create')) wp_die('Invalid nonce');

        $name = sanitize_text_field($_POST['plugin_name']);
        $slug = sanitize_title($_POST['plugin_slug']);
        $desc = sanitize_text_field($_POST['plugin_description']);
        $author = sanitize_text_field($_POST['plugin_author']);
        $ver = sanitize_text_field($_POST['plugin_version']);
        $code = $_POST['starter_code'] ?? '';
        $activate = isset($_POST['activate']);

        $dir = WP_PLUGIN_DIR.'/'.$slug;
        if(file_exists($dir)) wp_die('Plugin exists!');
        mkdir($dir.'/includes',0755,true);
        mkdir($dir.'/assets/js',0755,true);
        mkdir($dir.'/assets/css',0755,true);

        $main_file = $dir.'/'.$slug.'.php';
        $content = "<?php
/*
Plugin Name: $name
Description: $desc
Version: $ver
Author: $author
*/
if(!defined('ABSPATH')) exit;
register_activation_hook(__FILE__,function(){});
register_deactivation_hook(__FILE__,function(){});
$code
";
        file_put_contents($main_file,$content);

        if($activate) activate_plugin($slug.'/'.$slug.'.php');
        wp_redirect(admin_url('admin.php?page=ultimate-plugin-ide&success=1'));
        exit;
    }

    // Save file
    public function save_file(){
        if(!current_user_can('manage_options')) wp_die('Permission denied');
        $plugin = sanitize_text_field($_POST['plugin_slug']);
        $file = sanitize_text_field($_POST['file_path']);
        $content = $_POST['file_content'];
        $full = WP_PLUGIN_DIR.'/'.$plugin.'/'.$file;
        if(file_exists($full)) file_put_contents($full,$content);
        wp_redirect(admin_url('admin.php?page=ultimate-plugin-ide&success=1'));
        exit;
    }

    // AJAX: get plugin structure
    public function ajax_get_structure(){
        $plugin = sanitize_text_field($_POST['plugin']);
        echo $this->list_dir(WP_PLUGIN_DIR.'/'.$plugin);
        wp_die();
    }

    private function list_dir($dir,$base=''){
        $items = scandir($dir);
        $html = '<ul class="file-tree">';
        foreach($items as $item){
            if(in_array($item,['.','..'])) continue;
            $path = $base.'/'.$item;
            if(is_dir($dir.'/'.$item)){
                $html .= '<li class="folder"><strong>'.$item.'</strong> <button class="create-folder" data-path="'.ltrim($path,'/').'">+</button> <button class="create-file" data-path="'.ltrim($path,'/').'">File</button>'. $this->list_dir($dir.'/'.$item,$path).'</li>';
            }else{
                $html .= '<li class="file"><a href="#" class="file-link" data-file="'.ltrim($path,'/').'">'.$item.'</a> <button class="rename-item" data-file="'.ltrim($path,'/').'">Rename</button> <button class="delete-item" data-file="'.ltrim($path,'/').'">Delete</button></li>';
            }
        }
        $html .= '</ul>';
        return $html;
    }

    // AJAX: get file content
    public function ajax_get_file(){
        $plugin = sanitize_text_field($_POST['plugin']);
        $file = sanitize_text_field($_POST['file']);
        $full = WP_PLUGIN_DIR.'/'.$plugin.'/'.$file;
        if(file_exists($full)) echo file_get_contents($full);
        wp_die();
    }

    // AJAX: save file content
    public function ajax_save_file(){
        $plugin = sanitize_text_field($_POST['plugin']);
        $file = sanitize_text_field($_POST['file']);
        $content = $_POST['content'];
        $full = WP_PLUGIN_DIR.'/'.$plugin.'/'.$file;
        if(file_exists($full)) file_put_contents($full,$content);
        wp_die();
    }

    // AJAX: create file
    public function ajax_create_file(){
        $plugin = sanitize_text_field($_POST['plugin']);
        $path = sanitize_text_field($_POST['path']);
        $full = WP_PLUGIN_DIR.'/'.$plugin.'/'.$path.'/newfile.php';
        file_put_contents($full,"<?php\n// New file\n");
        echo 'ok'; wp_die();
    }

    // AJAX: create folder
    public function ajax_create_folder(){
        $plugin = sanitize_text_field($_POST['plugin']);
        $path = sanitize_text_field($_POST['path']);
        mkdir(WP_PLUGIN_DIR.'/'.$plugin.'/'.$path.'/new-folder',0755,true);
        echo 'ok'; wp_die();
    }

    // AJAX: rename
    public function ajax_rename_item(){
        $plugin = sanitize_text_field($_POST['plugin']);
        $old = sanitize_text_field($_POST['old']);
        $new = sanitize_text_field($_POST['new']);
        rename(WP_PLUGIN_DIR.'/'.$plugin.'/'.$old, WP_PLUGIN_DIR.'/'.$plugin.'/'.$new);
        echo 'ok'; wp_die();
    }

    // AJAX: delete
    public function ajax_delete_item(){
        $plugin = sanitize_text_field($_POST['plugin']);
        $file = sanitize_text_field($_POST['file']);
        $full = WP_PLUGIN_DIR.'/'.$plugin.'/'.$file;
        if(is_dir($full)) rmdir($full);
        else unlink($full);
        echo 'ok'; wp_die();
    }

    // AJAX: activate
    public function ajax_activate(){
        $plugin = sanitize_text_field($_POST['plugin']);
        activate_plugin($plugin.'/'.$plugin.'.php');
        wp_die();
    }

    // AJAX: deactivate
    public function ajax_deactivate(){
        $plugin = sanitize_text_field($_POST['plugin']);
        deactivate_plugins($plugin.'/'.$plugin.'.php');
        wp_die();
    }

    // AJAX: reorder files/folders
    public function ajax_reorder(){
        $plugin = sanitize_text_field($_POST['plugin']);
        $structure = wp_unslash($_POST['structure']); // JSON string
        $tree = json_decode($structure,true);
        $base_dir = WP_PLUGIN_DIR.'/'.$plugin;

        if($tree && is_array($tree)){
            $this->reorder_tree($tree,$base_dir);
        }
        wp_die();
    }

    // Recursive reorder function
    private function reorder_tree($tree,$dir){
        foreach($tree as $i=>$item){
            $old_path = $dir.'/'.$item['name'];
            $new_path = $dir.'/'.($i+1).'_'.basename($item['name']); // prefix order
            if(file_exists($old_path)) rename($old_path,$new_path);
            if($item['type']=='folder' && isset($item['children'])){
                $this->reorder_tree($item['children'],$new_path);
            }
        }
    }


    // Inline JS
    private function get_inline_js(){
        return <<<JS
jQuery(function($){
    var editor;
    function initEditor(){ 
        if(editor) editor.toTextArea(); 
        editor = CodeMirror.fromTextArea(document.getElementById("file-editor"),{
            lineNumbers:true,
            matchBrackets:true,
            indentUnit:4
        }); 
    }

    // Open plugin
    $('.open-plugin').click(function(){
        var plugin = $(this).data('plugin');
        $('#editing-plugin').text(plugin);
        $('#plugin-editor').show();
        $.post(ajaxurl,{action:'upide_get_structure',plugin:plugin},function(res){ 
            $('#file-tree').html(res); 
            initSortable();
        });
    });

    // Open file
    $('#file-tree').on('click','.file-link',function(e){
        e.preventDefault();
        var file = $(this).data('file');
        var plugin = $('#editing-plugin').text();
        $.post(ajaxurl,{action:'upide_get_file',plugin:plugin,file:file},function(res){
            $('#file-editor').val(res); 
            initEditor(); 
            $('#file-editor').data('file',file);
        });
    });

    // Save file
    $('#save-file').click(function(){
        var plugin = $('#editing-plugin').text();
        var file = $('#file-editor').data('file');
        var content = editor.getValue();
        $.post(ajaxurl,{action:'upide_save_file',plugin:plugin,file:file,content:content},function(){ alert('Saved'); });
    });

    // Drag & Drop (Sortable)
    function initSortable(){
        $('#file-tree ul').sortable({
            connectWith: '#file-tree ul',
            items: '> li',
            placeholder: 'sortable-placeholder',
            update: function(event, ui){
                var plugin = $('#editing-plugin').text();
                var structure = serializeTree($('#file-tree > ul > li'));
                $.post(ajaxurl,{action:'upide_reorder',plugin:plugin,structure:structure});
            }
        }).disableSelection();
    }

    // Serialize tree structure
    function serializeTree(items){
        var data = [];
        items.each(function(){
            var li = $(this);
            var item = {};
            if(li.hasClass('folder')){
                item.type='folder';
                item.name=li.children('strong').text();
                item.children=serializeTree(li.children('ul').children('li'));
            }else{
                item.type='file';
                item.name=li.children('a').data('file');
            }
            data.push(item);
        });
        return JSON.stringify(data);
    }
});

JS;
    }

    private function get_inline_css(){
        return <<<CSS
.file-tree, .file-tree ul{list-style:none;margin-left:20px;}
.file-tree li.folder > strong{cursor:pointer;}
.file-tree li.file a{cursor:pointer;text-decoration:underline;}
CSS;
    }
}

new Ultimate_Plugin_IDE();
