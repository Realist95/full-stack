<?php

class User {

    // GENERAL

    public static function user_info($d) {
        // vars
        $user_id = isset($d['user_id']) && is_numeric($d['user_id']) ? $d['user_id'] : 0;
        $phone = isset($d['phone']) ? preg_replace('~\D+~', '', $d['phone']) : 0;
        // where
        if ($user_id) $where = "user_id='".$user_id."'";
        else if ($phone) $where = "phone='".$phone."'";
        else return [];
        // info
        $q = DB::query("SELECT user_id, phone, access FROM users WHERE ".$where." LIMIT 1;") or die (DB::error());
        if ($row = DB::fetch_row($q)) {
            return [
                'id' => (int) $row['user_id'],
                'access' => (int) $row['access']
            ];
        } else {
            return [
                'id' => 0,
                'access' => 0
            ];
        }
    }

    public static function user_info_by_id($user_id) {
        // vars
        if($user_id <= 0) return null;

        $sql = <<<SQL
            SELECT `user_id`, `plot_id`, `first_name`, `last_name`, `email`, `phone`
            FROM `users`
            WHERE `user_id` = {$user_id} LIMIT 1;
        SQL;

        // info
        $q = DB::query($sql) or die (DB::error());

        while ($row = DB::fetch_row($q)) {
            return [
                'id'            => $row['user_id'],
                'plot_id'       => $row['plot_id'],
                'first_name'    => $row['first_name'],
                'last_name'     => $row['last_name'],
                'phone'         => phone_formatting($row['phone']),
                'email'         => $row['email'],
            ];
        }
        return null;
    }

    public static function users_list($d = []) {
        // vars
        $search = trim($d['search'] ?? '');
        $offset = (int) ($d['offset'] ?? 0);
        $limit = 20;
        $items = [];
        // where
        $where = [];
        if ($search) $where[] = "(`email` LIKE '%{$search}%' OR `phone` LIKE '%{$search}%' OR `first_name` LIKE '%{$search}%')";
        $where = $where ? "WHERE ".implode(" AND ", $where) : "";

        $sql = <<<SQL
            SELECT `user_id`, `plot_id`, `first_name`, `last_name`, `email`, `phone`, `last_login`, `updated` 
            FROM `users` {$where} ORDER BY `first_name` ASC LIMIT {$offset}, {$limit};
        SQL;

        // info
        $q = DB::query($sql) or die (DB::error());
        while ($row = DB::fetch_row($q)) {
            $items[] = [
                'id'            => $row['user_id'],
                'plot_id'       => $row['plot_id'],
                'first_name'    => $row['first_name'],
                'last_name'     => $row['last_name'],
                'phone'         => phone_formatting($row['phone']),
                'email'         => $row['email'],
                'last_login'    => date('Y/m/d', $row['last_login']),
            ];
        }
        // paginator
        $q = DB::query("SELECT count(*) FROM `users` ".$where.";");
        $count = ($row = DB::fetch_row($q)) ? $row['count(*)'] : 0;
        $url = 'users?';
        if ($search) $url .= '&search='.$search . ($count > $limit ? '&' : '');
        paginator($count, $offset, $limit, $url, $paginator);
        // output
        return ['items' => $items, 'paginator' => $paginator];
    }

    public static function user_edit_update($d = []) {
        // vars
        $user_id = (int) ($d['user_id'] ?? 0);

        $first_name = flt_input(trim($d['first_name'] ?? ''));
        if (empty($first_name)) {
            return error_response('400', 'First name is required', ['first_name']);
        }
        $last_name = flt_input(trim($d['last_name'] ?? ''));
        if (empty($last_name)) {
            return error_response('400', 'Last name is required', ['last_name']);
        }
        $phone = preg_replace('~\D+~', '', trim($d['phone'] ?? ''));
        if (empty($phone) || (strlen($phone) < 12 || strlen($phone) > 12)) {
            return error_response('400', 'The phone number must be at least 12 characters long', ['phone']);
        }
        $email = strtolower(trim($d['email'] ?? ''));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return error_response('400', "Email address '$email' is considered invalid.", ['email']);
        }

        $plot_id = flt_input(trim($d['plot_id'] ?? ''));

        $offset = isset($d['offset']) ? preg_replace('~\D+~', '', $d['offset']) : 0;

        $updated = Session::$ts;

        // update
        if ($user_id) {
            $set = [];
            $set[] = "first_name='{$first_name}'";
            $set[] = "last_name='{$last_name}'";
            $set[] = "phone='{$phone}'";
            $set[] = "email='{$email}'";
            $set[] = "plot_id='{$plot_id}'";
            $set[] = "updated='{$updated}'";
            $set = implode(", ", $set);
            DB::query("UPDATE `users` SET ".$set." WHERE `user_id`='{$user_id}' LIMIT 1;") or die (DB::error());
        } else {
            DB::query("INSERT INTO `users` (
                `first_name`,
                `last_name`,
                `phone`,
                `email`,
                `plot_id`,
                `updated`
            ) VALUES (
                '{$first_name}',
                '{$last_name}',
                '{$phone}',
                '{$email}',
                '{$plot_id}',
                '{$updated}'
            );") or die (DB::error());
        }
        // output
        return self::users_fetch(['offset' => $offset]);
    }

    public static function users_fetch($d = []): array {
        $info = self::users_list($d);
        HTML::assign('users', $info['items']);
        return ['html' => HTML::fetch('./partials/users_table.html'), 'paginator' => $info['paginator']];
    }

    public static function user_delete($d = []): array {
        $user_id = (int) ($d['user_id'] ?? 0);
        if(self::user_info_by_id($user_id) !== null) {
            DB::query("DELETE FROM `users` WHERE `user_id` = '{$user_id}';") or die (DB::error());
        }

        $offset = isset($d['offset']) ? preg_replace('~\D+~', '', $d['offset']) : 0;

        return self::users_fetch(['offset' => $offset]);
    }

    public static function user_edit_window($d = []): array {
        $user_id = (int) ($d['user_id'] ?? 0);
        HTML::assign('user', self::user_info_by_id($user_id));
        return ['html' => HTML::fetch('./partials/user_edit.html')];
    }

    public static function users_list_plots($number) {
        // vars
        $items = [];
        // info
        $q = DB::query("SELECT user_id, plot_id, first_name, email, phone
            FROM users WHERE plot_id LIKE '%".$number."%' ORDER BY user_id;") or die (DB::error());
        while ($row = DB::fetch_row($q)) {
            $plot_ids = explode(',', $row['plot_id']);
            $val = false;
            foreach($plot_ids as $plot_id) if ($plot_id == $number) $val = true;
            if ($val) $items[] = [
                'id' => (int) $row['user_id'],
                'first_name' => $row['first_name'],
                'email' => $row['email'],
                'phone_str' => phone_formatting($row['phone'])
            ];
        }
        // output
        return $items;
    }

}
