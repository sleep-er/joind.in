<?php

/* Just a few functions to send emails through out the site */
class SendEmail {
    
    private $_config;
    private $CI		= null;
    
    public function __construct() {
        $this->CI=&get_instance();
                $this->_config = $this->CI->config;
    }

    /**
    * Generic function for sending emails
    */
    private function _sendEmail($to, $msg, $subj, $from=null, $extra_headers=null) {
        if (!is_array($to)) { $to=array($to); }
        $from	= ($from) ? $from : $this->_config->item('email_feedback');
        $to 	= ($this->_config->item('debug_email')) ? array($this->_config->item('debug_email')) : $to;

        $headers = array();
        $headers[] = 'From: ' . $from;
        if (!empty($extra_headers)) {
            foreach ($extra_headers as $header) {
                $headers[]=$header;
            }
        }

        foreach ($to as $email) {
            mail($email, $subj, $msg, implode("\r\n", $headers), '-f'.$from);
        }
    }
    //-----------------------
    
    /**
    * Send a message to user who claimed the talk when its accepted
    */
    public function claimSuccess($to, $talk_title, $talk_id, $evt_name) {
        $subj= $this->_config->item('site_name') . ': Claim on talk "'.$talk_title.'"';
        $msg=sprintf("
You recently laid claim to a talk at the \"%s\" event on %s - \"%s\"
Your claim has been approved. This talk will now be listed under your account.

%stalk/view/%s

Thanks,
The %s Crew
        ", $evt_name, $this->_config->item('site_name'), $talk_title, 
        $this->_config->site_url(), $talk_id, $this->_config->item('site_name'));
        $this->_sendEmail($to, $msg, $subj);
    }

    /**
    * Send a notice of invite to a user (to a private event)
    */
    public function sendInvite($to, $evt_id, $evt_name) {
        $subj="You've been invited to ".$evt_name;
        $msg=sprintf("
You have been invited to the event \"%s\" (a private event)

To reply to this invite and add yourself to the attending list, please
visit %sevent/invite/%s/respond
        ", $evt_name, $this->_config->site_url(), $evt_id);

        $this->_sendEmail($to, $msg, $subj);
    }

    /**
    * Send a request to the event admin from a user wanting an invite
    * $user needs to be the result of a user_model->getUserBy*()
    */
    public function sendInviteRequest($eid, $evt_name, $user, $admins) {
        $subj='User '.$user[0]->full_name.' ('.$user[0]->username.') is requesting an invite!';
        $msg=sprintf("
The user %s (%s) has requested an invite to the event \"%s\"

To invite this user, visit %sevent/invite/%s and click on the \"Invite list\" to
approve or reject the invite.
        ", $user[0]->full_name, $user[0]->username, $evt_name, $this->_config->site_url(), $eid);
        
        //$to=array($user[0]->email);
        $to=array();
        foreach ($admins as $k=>$v) { $to[]=$v->email; }
        $this->_sendEmail($to, $msg, $subj);
    }
    
    /**
    * Send en email back to the event admins from the user
    * $admins should be a result of a user_model->getEventAdmins
    * $user needs to be the result of a user_model->getUserBy*()
    */
    public function sendEventContact($eid, $evt_name, $msg, $user, $admins) {
        $subj = $this->_config->item('site_name') . ': A question from '.$user[0]->username;
        $msg=sprintf("
%s (%s) has asked a question about the \"%s\" event:

%s

You can reply directly to them by replying to this email.
        ", $user[0]->full_name, $user[0]->username, $evt_name, $msg);
        
        $to=array();
        foreach ($admins as $k=>$v) { $to[]=$v->email; }
        $this->_sendEmail($to, $msg, $subj, $user[0]->email);
    }
    
    /**
    * Send password reset email to the given user 
    * (user's email address is looked up by username)
    */
    public function sendPasswordReset($user, $pass) {
        $to		= $user[0]->email;
        $subj	= $this->_config->item('site_name') . ' - Password Reset';
        $msg	= sprintf('
%s,

Your password has been reset for your account on %s.
Your new password is below:

%s

Please log in in at %suser/login and change your password as soon as possible.
        ', $user[0]->username, $this->_config->item('site_name'), $pass, $this->_config->site_url());
        $this->_sendEmail($to, $msg, $subj);
    }

    public function sendPasswordResetRequest($user, $request_code) {
        $to = $user[0]->email;
        $subj = $this->_config->item('site_name') . ' - Password Reset Requested';
        $msg = sprintf("
%s,

Someone has requested a password reset for your account on %s. If this wasn't you, don't worry. Nothing
has changed. In order to reset your password click on the link below or copy it into your browser:

%suser/forgot/%s/%s
        ", $user[0]->username, $this->_config->item('site_name'),
        $this->_config->site_url(), $user[0]->ID, $request_code);
        $this->_sendEmail($to, $msg, $subj);
    }
    
    /**
    * Send an email when a user is added to the admin list for an event
    */
    public function sendAdminAdd($user, $evt, $added_by=null) {
        $subj='You\'re now an admin on "'.$evt[0]->event_name.'"';
        $aby=($added_by) ? 'by '.$added_by : '';
        $msg=sprintf("
You have been added as an admin for the event \"%s\" %s

You can view the event here: %sevent/view/%s
        ", $evt[0]->event_name, $aby, $this->_config->site_url(), $evt[0]->ID);
        
        $to=array($user[0]->email);
        $this->_sendEmail($to, $msg, $subj);
    }
    
    /**
    * Send an email when a comment has been made on a session that's been claimed
    * Note: these emails are not sent to site admins
    * @param integer $tid Talk ID
    * @param string $to Email address
    * @param array $talk_detail Talk detail information
    * @param array $in_arr User data for byline
    */
    public function sendTalkComment($tid, $to, $talk_detail, $in_arr) {
        $CI =& get_instance();
        $byline='';
        if ($in_arr['user_id']!=0) {
            $CI->load->model('user_model');
            $udata	= $CI->user_model->getUserById($in_arr['user_id']);
            $byline	= 'by '.$udata[0]->full_name.' ('.$udata[0]->username.')';
        }
        
        $subj	= 'A new comment has been posted on your talk!';
        $msg	= sprintf("
A comment has been posted to your talk on %s %s: \n%s\n
%s
\n
Rating: %s
\n
Click here to view it: %stalk/view/%s
        ", $this->_config->item('site_name'), $byline, $talk_detail[0]->talk_title, trim($in_arr['comment']), $in_arr['rating'], $this->_config->site_url(), $tid);
        
        $to=array($to);
        $this->_sendEmail($to, $msg, $subj, $this->_config->item('email_comments'));
    }
    
    /**
    * Send an email when an event has successfully imported (from event/import)
    * @param $eid integer Event ID
    * @param $evt_detail array Event Detail information
    * @param $admins array Site admin information
    */
    public function sendSuccessfulImport($eid, $evt_detail, $admins=null) {
        $subj='Successful Import for event '.$evt_detail[0]->event_name;
        $from	= 'From:' . $this->_config->item('email_feedback');
        
        if (!$admins) { 
            $this->CI->load->model('event_model');
            $this->CI->event_model->getEventAdmins($eid); 
        }
        
        $msg=sprintf("
An import for the event %s has been successful.\n\n
You can view the event here: %sevent/view/%s
        ", $evt_detail[0]->event_name, $this->_config->site_url(), $eid);
        
        $to=array();
        foreach ($admins as $k=>$v) { $to[]=$v->email; }
        $this->_sendEmail($to, $msg, $subj, $this->_config->item('email_comments'));
    }
    
    /**
     * Send an email to the site admins about the currently pending events
     *
     */
    public function sendPendingEvents($pending_events)
    {
        $this->CI->load->model('user_model');
                
        $subj	= 'Pending Events on '.$this->_config->item('site_name');
        $from	= 'From:' . $this->_config->item('email_feedback');
        $admin 	= $this->CI->user_model->getSiteAdminEmail();
        foreach ($admin as $k=>$v) { $to[]=$v->email; }

        $msg		= "This is a list of pending events and their start dates. Don't miss one!\n\n";
        $event_list = array();
        $one_week 	= strtotime('+1 week');
        
        foreach ($pending_events as $event) {
            if ($event->event_start>time() && $event->event_start<=$one_week) {
                $event_list['One_Week'][]=$event;
            } elseif ($event->event_start<=time()) {
                $event_list['Past'][]=$event;
            } else {
                $event_list['Other'][]=$event;
            }
        }
        foreach ($event_list as $list_category => $list_item) {
            $msg.=str_replace('_',' ', $list_category)."\n";
            foreach ($list_item as $list_item_detail) {
                $msg.="\t".date('m.d.Y', $list_item_detail->event_start).': '.$list_item_detail->event_name."\n";
            }
        }
        
        $this->_sendEmail($to, $msg, $subj, $this->_config->item('email_comments'));
    }
    
    /**
     * Send the email when someone submits a claim on a talk
     *
     * @param array $talk_detail Talk details
     * @param array $to Email addresses
     */
    public function sendPendingClaim($talk_detail, $to)
    {
        error_log('sending pending');
        
        $subject	= 'Talk claim submitted! Go check!';
        $message	= sprintf("
Talk claim has been submitted for talk \"%s\"

Visit the link below to approve or deny the talk. Note: you must
be logged in to get to the \"Claims\" page for the event!

%sevent/claim/%s
        ", $talk_detail->talk_title, $this->CI->config->site_url(), $talk_detail->event_id);
        error_log('inside: '.$message);
    
        $this->_sendEmail($to, $message, $subject);
    }
    
    public function sendEventApproved($event_detail, $admin_list) {
        $subject 	= 'The event "'.$event_detail->event_name.'" has been approved!';
        $event_url 	= $this->_config->site_url().'event/view/'.$event_detail->ID;
        
        $msg 		= sprintf('
            <img src="%s/inc/img/logo.png" width="150"/>
            <br/>
            <b>%s</b> has been submitted to Joind.in and has been approved! You can see the event listing here: <a href="%s">%s</a>.
            <br/><br/>
            If you\'re not the one that submitted the event, it was probably a fan of the event who wanted it to be listed. Either way, thanks for having the event and we\'re glad to have it on Joind.in!
            <br/><br/>
            If you are the contact for the event and would like to be added as an event administrator, please visit the event listing and click the "Claim Event" button.
            <br/><br/>
            Please let us know if you have any questions about Joind.in, please don\'t hesitate to let us know!
            <br/></br>
            The Joind.in Team<br/>
            info@joind.in
        ', $this->_config->site_url(), $event_detail->event_name, $event_url, $event_url);
        
        $headers = array(
            'Content-Type: text/html; charset=ISO-8859-1',
            'From: '.$this->_config->item('email_feedback')
        );

        // Send to each event admin...
        foreach ($admin_list as $k=>$user) {
            $this->_sendEmail(array($user->email), $msg, $subject, null, $headers);
        }

    }
}
?>
