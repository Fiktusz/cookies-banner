<?php

class cookies
{
    private $visitor_id;
    private $cookie_variable = 'visitor_id';
    private $default;

    public function __construct()
    {
        global $COOKIES_CFG, $COOKIES_DB;

        if( !count( $COOKIES_DB->allArrays("SHOW TABLES LIKE ". $COOKIES_CFG['DB_PREFIX'] ."cookies_visitors") ) ){
            $this->create_basic_sql();
            header('Location:'. $_SERVER['REQUEST_URI'] );
            exit;
        }

        if( isset( $COOKIES_CFG['cookie_variable'] ) AND $COOKIES_CFG['cookie_variable'] ){
            $this->cookie_variable = $COOKIES_CFG['cookie_variable'];
        }

        if( !isset( $_COOKIE[ $this->cookie_variable ] ) ){
            $this->visitor_id = $this->new_visitor();
        } else {
            $this->visitor_id = $_COOKIE[ $this->cookie_variable ];

            if( !$this->check_visitor( $this->visitor_id ) ){
                $this->visitor_id = $this->new_visitor();
            }
        }

        $this->default = array(
            'panelClass' => 'cookies-panel',
            'title' => 'Aj náš web používa cookies',
            'img' => true,
            'img-src' => 'cookie-bite.svg',
            'description' => 'Aby  naše stránky správne fungovali, používame súbory nazývané cookies. Tieto malé súbory sa tiež používajú na analýzu návštevnosti alebo prispôsobenie obsahu a reklamy tak, aby sme Vám zobrazovali to, čo Vás najviac zaujíma. Prosím Vás o súhlas s používaním týchto súborov.',
            'show' => 'main',
            'parentDir' => 'cookies-banner/'
        );
    }

    private function new_visitor()
    {
        global $COOKIES_CFG, $COOKIES_DB;

        if( isset( $_COOKIE[ $this->cookie_variable ] ) )
            setcookie( $this->cookie_variable, null, -1, '/');

        //$visitor_id = hash('sha512', uniqid() . date('Y-m-d H:i:s'));
        $visitor_id = md5( uniqid() . time() );

        setcookie( $this->cookie_variable, $visitor_id, time() + ( 365 * 24 * 60 * 60 ), '/');

        if( $COOKIES_DB->nums("SELECT visitor_id FROM ". $COOKIES_CFG['DB_PREFIX'] ."cookies_visitors WHERE visitor_id = ?", array( $visitor_id )) ){
            $this->new_visitor();
            return;
        }

        $COOKIES_DB->insertData(
            "INSERT INTO ". $COOKIES_CFG['DB_PREFIX'] ."cookies_visitors(visitor_id) VALUES(?)",
            array( $visitor_id )
        );
    }

    private function check_visitor( $visitor_id )
    {
        global $COOKIES_CFG, $COOKIES_DB;

        if( $COOKIES_DB->nums("SELECT visitor_id FROM ". $COOKIES_CFG['DB_PREFIX'] ."cookies_visitors WHERE visitor_id = ?", array( $visitor_id )) ){
            return TRUE;
        }

        return FALSE;
    }

    public function confirmed( $category = null )
    {
        global $COOKIES_CFG, $COOKIES_DB;

        $categories = $this->get_categories();
        
        $sql = "SELECT category_id,confirm FROM ". $COOKIES_CFG['DB_PREFIX'] ."cookies_confirm WHERE visitor_id = ? AND active = ?";

        $confirms = array();
        foreach( $COOKIES_DB->allArrays( $sql, array( $this->visitor_id, 1 ) ) as $confirm ){
            $confirms[ $confirm['category_id'] ] = $confirm['confirm'];
        }

        if( !count( $categories ) ) return false;

        if( $category ){
            foreach( $categories as $value ){
                if(
                    $value['short-name'] == $category
                    AND (
                            (
                                isset( $confirms[ $value['id'] ] )
                                AND $confirms[ $value['id'] ]
                            )
                        OR $value['forced']
                    )
                )
                    return true;
            }
            return false;
        }

        foreach( $categories as $value )
        {
            if( !isset( $confirms[ $value['id'] ] ) )
                return false;
        }

        return true;
    }

    public function get_confirmed()
    {
        global $COOKIES_CFG, $COOKIES_DB;

        $categories = $this->get_categories();

        $sql = "SELECT category_id,confirm FROM ". $COOKIES_CFG['DB_PREFIX'] ."cookies_confirm WHERE visitor_id = ? AND active = ?";

        $confirms = array();
        foreach( $COOKIES_DB->allArrays( $sql, array( $this->visitor_id, 1 ) ) as $confirm ){
            $confirms[ $confirm['category_id'] ] = $confirm['confirm'];
        }

        if( !count( $categories ) ) return false;

        $confirmed = array();
        foreach( $categories as $value ) {
            if( isset( $confirms[ $value['id'] ] ) AND !$confirms[ $value['id'] ] ) continue;
            $confirmed[] = $value['short-name'];
        }

        return $confirmed;
    }

    public function get_categories( $data = null )
    {
        global $COOKIES_CFG, $COOKIES_DB;

        $select['table'] = $COOKIES_CFG['DB_PREFIX'] .'cookies_categories ccat';

        $select['columns'] = array(
            'ccat.`id`',
            'ccat.`short-name`',
            'ccat.`name`',
            'ccat.`forced`',
        );

        $select['join'] = array();

        $select['where']['columns'] = array( 'ccat.active = ?', 'AND ccat.info = ?' );
        $select['where']['values'] = array( 1, 0 );

        if( isset( $data['get'] ) AND is_array( $data['get'] ) )
        {
            if( in_array( 'info', $data['get'] ) ){
                $select['where']['columns'] = array( 'ccat.active = ?' );
                $select['where']['values'] = array( 1 );
            }
        }

        $select['order']['columns'] = array( 'ordered', 'id' );
        $select['order']['sort'] = array( 'ASC', 'ASC' );

        if( isset( $data['get'] ) ){
            foreach( $data['get'] as $get ){
                $select['columns'][] = 'ccat.'. $get;
            }
        }

        $sql = $COOKIES_DB->createSQL( $select, 'select' );
        $categories = $COOKIES_DB->allArrays( $sql, $select['where']['values'] );

        return $categories;
    }

    public function allow_all( $data = null )
    {
        global $COOKIES_CFG, $COOKIES_DB;

        $result['success'] = false;
        $result['message'] = '';

        $this->remove_confirms();

        $sql = "INSERT INTO ". $COOKIES_CFG['DB_PREFIX'] ."cookies_confirm(visitor_id,category_id,confirm) VALUES(?,?,?)";

        foreach( $this->get_categories() as $category ){
            $COOKIES_DB->insertData(
                $sql,
                array(
                    $this->visitor_id,
                    $category['id'],
                    1
                )
            );
        }

        $result['success'] = true;

        return $result;
    }

    public function allow_selected( $data = null )
    {
        global $COOKIES_CFG, $COOKIES_DB;

        $result['success'] = false;
        $result['message'] = '';

        if( !isset( $data['values'] ) ){
            $result['message'] = 'Missing categories';
            return $result;
        }

        $this->remove_confirms();

        $sql = "INSERT INTO ". $COOKIES_CFG['DB_PREFIX'] ."cookies_confirm(visitor_id,category_id,confirm) VALUES(?,?,?)";

        foreach( $this->get_categories() as $category ){
            $confirm = ( in_array( $category['id'], $data['values'] ) ) ? 1 : 0;
            if( $category['forced'] ) $confirm = 1;

            $COOKIES_DB->insertData(
                $sql,
                array(
                    $this->visitor_id,
                    $category['id'],
                    $confirm
                )
            );
        }

        $result['success'] = true;

        return $result;
    }

    public function deny_all( $data = null )
    {
        global $COOKIES_CFG, $COOKIES_DB;

        $result['success'] = false;
        $result['message'] = '';

        $this->remove_confirms();

        $sql = "INSERT INTO ". $COOKIES_CFG['DB_PREFIX'] ."cookies_confirm(visitor_id,category_id,confirm) VALUES(?,?,?)";

        foreach( $this->get_categories() as $category ){
            $COOKIES_DB->insertData(
                $sql,
                array(
                    $this->visitor_id,
                    $category['id'],
                    $category['forced']
                )
            );
        }

        $result['success'] = true;

        return $result;
    }

    private function remove_confirms( $data = null )
    {
        global $COOKIES_CFG, $COOKIES_DB;

        $COOKIES_DB->updateData("UPDATE ". $COOKIES_CFG['DB_PREFIX'] ."cookies_confirm SET active = ? WHERE visitor_id = ?", array( 0, $this->visitor_id ));
    }

    public function get_cookies( $data = null )
    {
        global $COOKIES_CFG, $COOKIES_DB;

        $select['table'] = $COOKIES_CFG['DB_PREFIX'] .'cookies_details cdet';

        $select['columns'] = array(
            'cdet.`id`',
            'cdet.`category_id`',
            'cdet.`name`',
            'cdet.`domain`',
            'cdet.`max-age`',
            'cdet.`provenance`',
            'cdet.`description`',
            "ccat.`short-name` AS 'category_short_name'",
            "ccat.`name` AS 'category_name'",
        );

        $select['join'] = array( 'LEFT JOIN '. $COOKIES_CFG['DB_PREFIX'] .'cookies_categories ccat ON cdet.category_id = ccat.id' );

        $select['where']['columns'] = array('cdet.active = ?');
        $select['where']['values'] = array( 1 );

        $select['order']['columns'] = array( 'cdet.name', 'cdet.id' );
        $select['order']['sort'] = array( 'ASC', 'ASC' );

        if( isset( $data['category_id'] ) )
        {
            $select['where']['columns'][] = 'AND cdet.category_id = ?';
            $select['where']['values'][] = $data['category_id'];
        }

        $sql = $COOKIES_DB->createSQL( $select, 'select' );
        $cookies = $COOKIES_DB->allArrays( $sql, $select['where']['values'] );

        return $cookies;
    }

    private function insert_cookie( $name )
    {
        global $COOKIES_CFG, $COOKIES_DB;

        $categories = $this->get_categories( array( 'get' => array( 'info' ) ) );
        $ids = array();

        foreach( $categories as $category ){
            $ids[ $category['short-name'] ] = $category['id'];
        }

        $category_id = null;
        if( !$category_id AND $name == $this->cookie_variable ){ $category_id = $ids['necessary']; }

        if( !$category_id ){ $category_id = $ids['unclassified']; }

        $sql = "INSERT INTO ". $COOKIES_CFG['DB_PREFIX'] ."cookies_details(category_id,`name`) VALUES(?,?)";
        $params = array( $category_id, $name );

        $COOKIES_DB->insertData( $sql, $params );
    }

    public function collect_cookies()
    {
        if( !isset( $_COOKIE ) OR !count( $_COOKIE ) ) return FALSE;
        
        $cookies = $this->get_cookies();
        $check = array();
        foreach( $cookies as $cookie ){
            $check[ $cookie['name'] ] = $cookie;
        }

        foreach( $_COOKIE as $key => $value ){
            if( isset( $check[ $key ] ) ) continue;

            $this->insert_cookie( $key );
        }
    }

    private function save_cookies( $data )
    {
        global $COOKIES_CFG, $COOKIES_DB;

        if( !isset( $data['item'] ) ) return FALSE;

        $items = $data['item'];

        $sql = "
            UPDATE ". $COOKIES_CFG['DB_PREFIX'] ."cookies_details
            SET
                `category_id` = ?,
                `domain` = ?,
                `max-age` = ?,
                `provenance` = ?,
                `description` = ?
            WHERE id = ?
        ";

        foreach( $items as $id => $detail ){
            $detail['max-age'] = ( $detail['max-age'] ) ? $detail['max-age'] : 0;

            $params = array(
                $detail['category_id'],
                $detail['domain'],
                $detail['max-age'],
                $detail['provenance'],
                $detail['description'],
                $id
            );

            $COOKIES_DB->updateData( $sql, $params );
        }

        return TRUE;
    }

    public function render_panel( $data = array() )
    {
        if(
            $this->confirmed()
            AND !isset( $data['show'] )
        ){
            if( rand( 0, 2 ) == 1 ) $this->collect_cookies();
            return '';
        }

        $settings = array_merge( $this->default, $data );

        switch( $settings['show'] ){
            case 'main':
                $html = '';
                $html .= '<div class="'. $settings['panelClass'] .'">';
                    $html .= '<img class="cancel" src="'. $settings['parentDir'] .'xmark-solid.svg" alt="close" title="Zatvoriť" />';
                    $html .= '<div class="title">';
                        if( $settings['img'] )
                        $html .= '<img src="'. $settings['parentDir'] . $settings['img-src'] .'" alt="icon" title="Cookies" />';
                        $html .= $settings['title'];
                    $html .= '</div>';
                    $html .= '<div class="description">';
                        $html .= $settings['description'];
                    $html .= '</div>';
                    $html .= '<button class="allow-all">Povoliť všetko</button>';
                    $html .= '<button class="deny-all">Odmietnuť všetko</button>';
                    $html .= '<button class="transparent more">Viac možností</button>';
                $html .= '</div>';

                return $html;
                break;
            case 'more':
                $categories = $this->get_categories( array( 'get' => array( 'description' ) ) );

                $html = '';
                $html .= '<div class="'. $settings['panelClass'] .'">';
                    $html .= '<img class="cancel" src="'. $settings['parentDir'] .'xmark-solid.svg" alt="close" title="Zatvoriť" />';
                    $html .= '<div class="body">';
                    foreach( $categories as $category ){
                    $html .= '<div class="group">';
                        $html .= '<div class="value">';
                            $html .= '<label>';
                                $disabled = ( $category['forced'] ) ? 'disabled selected' : '';
                                $html .= '<span class="checkbox '. $disabled .'" data-value="'. $category['id'] .'"><img src="'. $settings['parentDir'] . 'check-solid.svg" alt="check" title="Checkbox" /></span>';
                                $html .= '<span>'. $category['name'] .'</span>';
                            $html .= '</label>';
                        $html .= '</div>';
                        if( $category['description'] ){
                            $html .= '<div class="description">'. $category['description'] .'</div>';
                        }
                    $html .= '</div>';
                    }
                    $html .= '</div>';
                    $html .= '<button class="allow-selected">Povoliť vybrané</button>';
                    $html .= '<button class="transparent back">Späť</button>';
                $html .= '</div>';

                return $html;
                break;
            case 'only-more':
                $categories = $this->get_categories( array( 'get' => array( 'description' ) ) );

                $html = '';
                $html .= '<div class="'. $settings['panelClass'] .'">';
                    $html .= '<img class="cancel" src="'. $settings['parentDir'] .'xmark-solid.svg" alt="close" title="Zatvoriť" />';
                    $html .= '<div class="body">';
                    foreach( $categories as $category ){
                    $html .= '<div class="group">';
                        $html .= '<div class="value">';
                            $html .= '<label>';
                                $disabled = ( $category['forced'] ) ? 'disabled' : '';
                                $selected = ( $this->confirmed( $category['short-name'] ) ) ? 'selected' : '';
                                $html .= '<span class="checkbox '. $disabled .' '. $selected .'" data-value="'. $category['id'] .'"><img src="'. $settings['parentDir'] . 'check-solid.svg" alt="check" title="Checkbox" /></span>';
                                $html .= '<span>'. $category['name'] .'</span>';
                            $html .= '</label>';
                        $html .= '</div>';
                        if( $category['description'] ){
                            $html .= '<div class="description">'. $category['description'] .'</div>';
                        }
                    $html .= '</div>';
                    }
                    $html .= '</div>';
                    $html .= '<button class="allow-selected">Povoliť vybrané</button>';
                    $html .= '<button class="transparent cancel">Zrušiť</button>';
                $html .= '</div>';

                return $html;
                break;
        }
    }

    public function render_adminpage( $data = array() )
    {
        if( isset( $_GET ) AND count( $_GET ) ){
            foreach( $_GET as $key => $value ){
                $data[$key] = $value;
            }
        }

        $settings = array_merge( $this->default, $data );

        $html = '';
        
        $html .= '<!DOCTYPE html>';
        $html .= '<html>';
            $html .= '<head>';
                $html .= '<meta charset="utf-8">';
                $html .= '<meta name="robots" content="noindex,nofollow">';
                $html .= '<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0, viewport-fit=cover">';
                $html .= '<title>Cookies</title>';
                $html .= '<meta name="description" content="">';
                $html .= '<meta name="author" content="">';
                $html .= '<link rel="stylesheet" type="text/css" href="cookies.css?v='. rand( 0, 9999 ) .'" />';
                $html .= '<style>';
                $html .= 'html{margin:0;padding:0;min-width:100%;min-height:100%;}';
                $html .= '</style>';
            $html .= '</head>';
            $html .= '<body id="admin-page">';
                $html .= '<div class="'. $settings['panelClass'] .'">';
                    if( !$this->is_logged() ) :
                    $html .= '<div class="title">';
                        $html .= 'Prihlásenie';
                    $html .= '</div>';
                    if( isset( $settings['message'] ) AND !empty( $settings['message'] ) ){
                    $html .= '<div class="description">';
                        $html .= $settings['message'];
                    $html .= '</div>';
                    }
                    $html .= '<form action="" method="post">';
                        $html .= '<div class="group">';
                            $html .= '<div class="name">Prihlasovacie meno</div>';
                            $html .= '<div class="value"><input type="text" name="username" /></div>';
                        $html .= '</div>';
                        $html .= '<div class="group">';
                            $html .= '<div class="name">Prihlasovacie heslo</div>';
                            $html .= '<div class="value"><input type="password" name="password" /></div>';
                        $html .= '</div>';
                        $html .= '<button>Prihlásiť</button>';
                        $html .= '<input type="hidden" name="action" value="login" />';
                    $html .= '</form>';
                    else:
                    $html .= '<div class="title">Administrácia</div>';
                    $html .= '<div class="body">';
                    
                    $categories = $this->get_categories( array( 'get' => array( 'info' ) ) );

                    foreach( $categories as $category ){
                        $cookies = $this->get_cookies( array( 'category_id' => $category['id'] ) );
                        $html .= '<div class="section">'. $category['name'] .' ('. count( $cookies ) .')</div>';

                        if( count( $cookies ) ){
                            $html .= '<div class="details">';
                            $html .= '<form action="" method="post">';
                            foreach( $cookies as $cookie ){
                                $html .= '<div class="item">';
                                    $html .= '<div class="category">';
                                        $html .= '<select name="item['. $cookie['id'] .'][category_id]">';
                                        $html .= '<option value="">&nbsp;</option>';
                                        foreach( $categories as $caty ){
                                            $selected = ( $caty['id'] == $cookie['category_id'] ) ? 'selected' : '';
                                            $html .= '<option value="'. $caty['id'] .'" '. $selected .'>'. $caty['name'] .'</option>';
                                        }
                                        $html .= '</select>';
                                    $html .= '</div>';
                                    $html .= '<div class="name">'. $cookie['name'] .'</div>';
                                    $html .= '<div class="more">';
                                        $html .= 'domain <input type="text" name="item['. $cookie['id'] .'][domain]" value="'. $cookie['domain'] .'" /><br/>';
                                        $html .= 'max-age <input type="text" name="item['. $cookie['id'] .'][max-age]" value="'. $cookie['max-age'] .'" /><br/>';
                                        $html .= 'provenance <input type="text" name="item['. $cookie['id'] .'][provenance]" value="'. $cookie['provenance'] .'" /><br/>';
                                        $html .= 'description<br/><textarea name="item['. $cookie['id'] .'][description]">'. $cookie['description'] .'</textarea><br/>';
                                    $html .= '</div>';
                                $html .= '</div>';
                            }
                            $html .= '<button>Uložiť</button>';
                            $html .= '<input type="hidden" name="action" value="save" />';
                            $html .= '</form>';
                            $html .= '</div>';
                        }
                    }
                    $html .= '</div>';

                    $html .= '<form action="" method="post">';
                        $html .= '<button>Odhlásiť</button>';
                        $html .= '<input type="hidden" name="action" value="logout" />';
                    $html .= '</form>';
                    endif;
                $html .= '</div>';
                $html .= '<script src="jquery-3.3.1.min.js" type="text/javascript"></script>';
                $html .= '<script src="cookies.js?v='. rand( 0, 9999 ) .'" type="text/javascript"></script>';
                $html .= '<script type="text/javascript">';
                    $html .= '$(document).ready( function(){';
                        $html .= '$("body#admin-page").cookies().adminPage();';
                    $html .= '});';
                $html .= '</script>';
            $html .= '</body>';
        $html .= '</html>';

        return $html;
    }

    public function admin_actions( $data )
    {
        $redirect = array();

        if( !isset( $data['action'] ) ){
            $this->admin_redirect( array( 'message' => 'missing action' ) );
        }

        switch( $data['action'] ){
            case 'login':
                $redirect = $this->login( $data );
                break;
            case 'logout':
                $this->logout();
                break;
            case 'save':
                if( !$this->is_logged() ){
                    $this->admin_redirect( array( 'message' => 'not logged' ) );
                }
                $redirect = $this->save_cookies( $data );
                break;
        }

        $this->admin_redirect( $redirect );
    }

    private function login( $data )
    {
        global $COOKIES_CFG, $COOKIES_DB;

        $sql = "SELECT `id`,`username`,`password` FROM ". $COOKIES_CFG['DB_PREFIX'] ."cookies_admins WHERE username=? AND active=?";
		$params = array( $data['username'], 1 );

        $users = $COOKIES_DB->allArrays( $sql, $params );

        $data['password'] = hash('sha512', $COOKIES_CFG['HASH'] . $data['password']);

		foreach( $users as $value )
		{
			if ( strtoupper( $value['username'] ) == strtoupper( $data['username'] ) && $value['password'] == $data['password'] )
			{	
				$_SESSION[ $COOKIES_CFG['HASH'] ]['id'] = $value['id'];
				return TRUE;
			}
		}

        $this->admin_redirect( array( 'message' => 'Zlé meno alebo heslo' ) );
    }

    private function logout()
	{
		global $COOKIES_CFG;
		
		unset( $_SESSION[ $COOKIES_CFG['HASH'] ] );

		$this->admin_redirect( array( 'message' => 'Boli ste úspešne odhlásený' ) );
	}

    private function is_logged()
    {
        global $COOKIES_CFG;

        if( isset( $_SESSION[ $COOKIES_CFG['HASH'] ]['id'] ) ) return TRUE;
        return FALSE;
    }

    private function admin_redirect( $data = array() )
    {
        $parameters = '';
        if( is_array( $data ) AND count( $data ) ){
            $parameters = '?'. http_build_query( $data );
        }

        $base = $_SERVER['REQUEST_SCHEME'] .'://'. $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'] . $parameters;
        header('Location: '. $base );
        exit;
    }

    /* SQL */
    private function create_basic_sql()
    {
        global $COOKIES_CFG, $COOKIES_DB;

        $sql = "
        CREATE TABLE `". $COOKIES_CFG['DB_PREFIX'] ."cookies_admins` (
            `id` int NOT NULL,
            `username` varchar(64) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
            `password` varchar(128) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL,
            `date_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `date_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `active` int NOT NULL DEFAULT '1'
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;
          
          INSERT INTO `". $COOKIES_CFG['DB_PREFIX'] ."cookies_admins` (`id`, `username`, `password`, `date_updated`, `date_created`, `active`) VALUES
          (1, 'cookie-admin', '48c873f4eb30e5c4a1d1f78f9644744edfeb5d4a8e646cc521e90ebeb8a60c1a43dc9afe9280b8d1b3117280cacd11ed1f584444282e543693685b415884a8ad', '2022-05-23 09:48:04', '2022-05-23 06:06:31', 1);
          
          CREATE TABLE `". $COOKIES_CFG['DB_PREFIX'] ."cookies_categories` (
            `id` int NOT NULL,
            `short-name` varchar(16) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL,
            `name` varchar(64) NOT NULL,
            `description` text,
            `forced` tinyint(1) NOT NULL DEFAULT '0',
            `info` tinyint(1) NOT NULL DEFAULT '0',
            `ordered` int DEFAULT NULL,
            `date_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `date_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `active` tinyint(1) NOT NULL DEFAULT '1'
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
          
          
          INSERT INTO `". $COOKIES_CFG['DB_PREFIX'] ."cookies_categories` (`id`, `short-name`, `name`, `description`, `forced`, `info`, `ordered`, `date_updated`, `date_created`, `active`) VALUES
          (1, 'necessary', 'Potrebné', 'Potrebné súbory cookie pomáhajú vytvárať použiteľné webové stránky tak, že umožňujú základné funkcie, ako je navigácia stránky a prístup k chráneným oblastiam webových stránok. Webové stránky nemôžu riadne fungovať bez týchto súborov cookies.', 1, 0, 0, '2022-05-23 07:17:14', '2022-05-19 14:27:19', 1),
          (2, 'preferences', 'Preferencie', 'Preferenčné súbory cookies umožňujú internetovej stránke zapamätať si informácie, ktoré zmenia spôsob, akým sa webová stránka chová alebo vyzerá, ako napr. váš preferovaný jazyk alebo región, v ktorom sa práve nachádzate.', 0, 0, 1, '2022-05-23 07:17:34', '2022-05-19 14:27:19', 1),
          (3, 'statistics', 'Štatistiky', 'Štatistické súbory cookies pomáhajú majiteľom webových stránok, aby pochopili, ako komunikovať s návštevníkmi webových stránok prostredníctvom zberu a hlásenia informácií anonymne.', 0, 0, 2, '2022-05-23 07:18:08', '2022-05-19 14:27:48', 1),
          (4, 'marketing', 'Marketing', 'Marketingové súbory cookies sa používajú na sledovanie návštevníkov na webových stránkach. Zámerom je zobrazovať reklamy, ktoré sú relevantné a pútavé pre jednotlivých užívateľov, a tým cennejšie pre vydavateľov a inzerentov tretích strán.', 0, 0, 3, '2022-05-23 07:18:31', '2022-05-19 14:27:48', 1),
          (5, 'unclassified', 'Nezaradené', 'Nezaradené súbory cookies sú cookies, ktoré práve zaraďujeme, spoločne s poskytovateľmi jednotlivých súborov cookies.', 0, 1, 4, '2022-05-23 07:18:31', '2022-05-19 14:27:48', 1);
          
          CREATE TABLE `". $COOKIES_CFG['DB_PREFIX'] ."cookies_confirm` (
            `id` int NOT NULL,
            `visitor_id` varchar(128) NOT NULL,
            `category_id` int NOT NULL,
            `confirm` tinyint(1) NOT NULL,
            `date_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `date_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `active` tinyint(1) NOT NULL DEFAULT '1'
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
          
          CREATE TABLE `". $COOKIES_CFG['DB_PREFIX'] ."cookies_details` (
            `id` int NOT NULL,
            `category_id` int NOT NULL,
            `name` varchar(255) NOT NULL,
            `domain` varchar(255) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
            `max-age` int DEFAULT NULL,
            `provenance` varchar(32) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL,
            `description` text CHARACTER SET utf8 COLLATE utf8_general_ci,
            `date_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            `date_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `active` tinyint(1) NOT NULL DEFAULT '1'
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
          
          CREATE TABLE `". $COOKIES_CFG['DB_PREFIX'] ."cookies_visitors` (
            `visitor_id` varchar(128) NOT NULL,
            `date_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `active` tinyint(1) NOT NULL DEFAULT '1'
          ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
          
          ALTER TABLE `". $COOKIES_CFG['DB_PREFIX'] ."cookies_admins`
            ADD PRIMARY KEY (`id`);
          
          
          ALTER TABLE `". $COOKIES_CFG['DB_PREFIX'] ."cookies_categories`
            ADD PRIMARY KEY (`id`);
          
          
          ALTER TABLE `". $COOKIES_CFG['DB_PREFIX'] ."cookies_confirm`
            ADD PRIMARY KEY (`id`),
            ADD KEY `visitor_id` (`visitor_id`),
            ADD KEY `category_id` (`category_id`);
          
          
          ALTER TABLE `". $COOKIES_CFG['DB_PREFIX'] ."cookies_details`
            ADD PRIMARY KEY (`id`),
            ADD KEY `category_id` (`category_id`);
          
          
          ALTER TABLE `". $COOKIES_CFG['DB_PREFIX'] ."cookies_visitors`
            ADD PRIMARY KEY (`visitor_id`);
          
          ALTER TABLE `". $COOKIES_CFG['DB_PREFIX'] ."cookies_confirm`
            ADD CONSTRAINT `". $COOKIES_CFG['DB_PREFIX'] ."cookies_confirm_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `". $COOKIES_CFG['DB_PREFIX'] ."cookies_categories` (`id`),
            ADD CONSTRAINT `". $COOKIES_CFG['DB_PREFIX'] ."cookies_confirm_ibfk_3` FOREIGN KEY (`visitor_id`) REFERENCES `". $COOKIES_CFG['DB_PREFIX'] ."cookies_visitors` (`visitor_id`) ON DELETE RESTRICT ON UPDATE RESTRICT;
          
          ALTER TABLE `". $COOKIES_CFG['DB_PREFIX'] ."cookies_details`
            ADD CONSTRAINT `". $COOKIES_CFG['DB_PREFIX'] ."cookies_details_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `". $COOKIES_CFG['DB_PREFIX'] ."cookies_categories` (`id`);
          COMMIT;
        ";

        echo '<pre>';
        echo $COOKIES_DB->debug( $sql, array() );
        echo '</pre>';
        exit;
        $COOKIES_DB->updateData( $sql );
    }
}

?>