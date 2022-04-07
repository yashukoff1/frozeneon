<?php

use Model\Boosterpack_model;
use Model\Boosterpack_info_model;
use Model\Post_model;
use Model\User_model;
use Model\Login_model;
use Model\Comment_model;
use Model\Analytics_model;
use Model\Enum\Transaction_type;
use Model\Enum\Transaction_info;

/**
 * Created by PhpStorm.
 * User: mr.incognito
 * Date: 10.11.2018
 * Time: 21:36
 */
class Main_page extends MY_Controller
{

    public function __construct()
    {

        parent::__construct();

        if (is_prod())
        {
            die('In production it will be hard to debug! Run as development environment!');
        }
    }

    public function index()
    {
        $user = User_model::get_user();

        App::get_ci()->load->view('main_page', ['user' => User_model::preparation($user, 'default')]);
    }

    public function get_all_posts()
    {
        $posts =  Post_model::preparation_many(Post_model::get_all(), 'default');
        return $this->response_success(['posts' => $posts]);
    }

    public function get_boosterpacks()
    {
        $posts =  Boosterpack_model::preparation_many(Boosterpack_model::get_all(), 'default');
        return $this->response_success(['boosterpacks' => $posts]);
    }

    public function login()
    {
        // TODO: task 1, аутентификация

        $login = (string)App::get_ci()->input->post('login');
        $password = (string)App::get_ci()->input->post('password');

        $user = User_model::find_user_by_email($login);

        if (!$user->is_loaded() || $user->get_password() != $password){
            return $this->response_error(System\Libraries\Core::RESPONSE_GENERIC_NO_DATA); 
        }

        Login_model::login($user);

        return $this->response_success(['user' => User_model::preparation($user, 'main_page')]);
    }

    public function logout()
    {
        // TODO: task 1, аутентификация

        Login_model::logout();
        return $this->response_success();
    }

    public function comment()
    {
        // TODO: task 2, комментирование
        $post_data = App::get_ci()->input->post();
        $user = User_model::get_user();

        if (!$user->is_loaded()){
            return $this->response_error(System\Libraries\Core::RESPONSE_GENERIC_NEED_AUTH); 
        }

        $data = [
            'user_id' => $user->get_id(),
            'assign_id' => $post_data['postId'],
            'text' => $post_data['commentText'],
            'likes' => 0
        ];

        if(isset($post_data['commentId'])){
            $data['reply_id'] = $post_data['commentId'];
        }

        $comment = Comment_model::create($data);

        if($comment){
            return $this->response_success(['comment' => Comment_model::preparation($comment)]);
        }else{
            return $this->response_error(System\Libraries\Core::RESPONSE_GENERIC_INTERNAL_ERROR); 
        }
    }

    public function like_comment(int $comment_id)
    {
        // TODO: task 3, лайк комментария
        $user = User_model::get_user();

        if (!$user->is_loaded()){
            return $this->response_error(System\Libraries\Core::RESPONSE_GENERIC_NEED_AUTH); 
        }

        if ($user->get_likes_balance() < 1){
            return $this->response_error(System\Libraries\Core::RESPONSE_GENERIC_UNAVAILABLE); 
        }

        $comment = new Comment_model($comment_id);

        if (!$comment->is_loaded()) {
            return $this->response_error(System\Libraries\Core::RESPONSE_GENERIC_NO_DATA);
        }

        if ($comment->increment_likes($user)) {

            $comment = $comment->reload();

            return $this->response_success(['likes' => $comment->get_likes()]);
        }else{
            return $this->response_error(System\Libraries\Core::RESPONSE_GENERIC_TRY_LATER);
        }
    }

    public function like_post(int $post_id)
    {
        // TODO: task 3, лайк поста
        $user = User_model::get_user();

        if (!$user->is_loaded()){
            return $this->response_error(System\Libraries\Core::RESPONSE_GENERIC_NEED_AUTH); 
        }

        if ($user->get_likes_balance() < 1){
            return $this->response_error(System\Libraries\Core::RESPONSE_GENERIC_UNAVAILABLE); 
        }

        $post = new Post_model($post_id);

        if (!$post->is_loaded()) {
            return $this->response_error(System\Libraries\Core::RESPONSE_GENERIC_NO_DATA);
        }

        if ($post->increment_likes($user)) {

            $post = $post->reload();

            return $this->response_success(['likes' => $post->get_likes()]);
        }else{
            return $this->response_error(System\Libraries\Core::RESPONSE_GENERIC_TRY_LATER);
        }

    }

    public function add_money()
    {
        // TODO: task 4, пополнение баланса

        $user = User_model::get_user();

        if (!$user->is_loaded()){
            return $this->response_error(System\Libraries\Core::RESPONSE_GENERIC_NEED_AUTH); 
        }

        $sum = (float)App::get_ci()->input->post('sum');

        if ($user->add_money($sum)) {

            $user = $user->reload();

            $data = [
                'user_id' => User_model::get_session_id(),
                'object' => Transaction_type::INCOME,
                'action' => Transaction_info::ADD_MONEY_TO_WALLET,
                'amount' => $sum
            ];

            Analytics_model::create($data);

            return $this->response_success(['user' => User_model::preparation($user, 'main_page')]);
        }else{
            return $this->response_error(System\Libraries\Core::RESPONSE_GENERIC_TRY_LATER);
        }

    }

    public function get_post(int $post_id) {
        // TODO получения поста по id
        $post = new Post_model($post_id);

        if ($post->is_loaded()) {
            return $this->response_success(['post' => Post_model::preparation($post, 'full_info')]);
        }

        return $this->response_error(System\Libraries\Core::RESPONSE_GENERIC_NO_DATA);
    }

    public function buy_boosterpack()
    {
        // Check user is authorize
        if ( ! User_model::is_logged())
        {
            return $this->response_error(System\Libraries\Core::RESPONSE_GENERIC_NEED_AUTH);
        }

        // TODO: task 5, покупка и открытие бустерпака

        $id = (int)App::get_ci()->input->post('id');

        $boosterpack = new Boosterpack_model($id);

        $max_available_likes = $boosterpack->get_bank() + $boosterpack->get_price() - $boosterpack->get_us();

        $current_item = $boosterpack->get_contains($max_available_likes);

        if(empty($current_item)){
            return $this->response_error(System\Libraries\Core::RESPONSE_GENERIC_NO_DATA);
        }

        $amount = $boosterpack->open();

        $data = [
            'user_id' => User_model::get_session_id(),
            'object' => Transaction_type::EXPENSE,
            'action' => Transaction_info::BUY_BOOSTERPACK,
            'object_id' => $boosterpack->get_id(),
            'amount' => $amount
        ];

        Analytics_model::create($data);

        return $this->response_success(['amount' => $amount]);

    }





    /**
     * @return object|string|void
     */
    public function get_boosterpack_info(int $bootserpack_info)
    {
        // Check user is authorize
        if ( ! User_model::is_logged())
        {
            return $this->response_error(System\Libraries\Core::RESPONSE_GENERIC_NEED_AUTH);
        }


        //TODO получить содержимое бустерпака
    }
}
