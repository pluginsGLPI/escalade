<?php

class PluginEscaladeNotification {
   const NTRGT_WATCHERS              = 357951;
   const NTRGT_TICKET_GROUP          = 357952;
   const NTRGT_TICKET_TECHNICIAN     = 357953;
   const NTRGT_TICKET_GROUP_MANAGER  = 357954;
   const NTRGT_TASK_GROUP            = 357955;

   static function addTargets(NotificationTarget $target) {
      // add targets to planning recall notification
      if ($target instanceof NotificationTargetPlanningRecall) {
         $target->addTarget(self::NTRGT_WATCHERS,             _n('Watcher', 'Watchers', 2));
         $target->addTarget(self::NTRGT_TICKET_GROUP,         __('Group in charge of the ticket'));
         $target->addTarget(self::NTRGT_TICKET_TECHNICIAN,    __('Manager of the group in charge of the ticket'));
         $target->addTarget(self::NTRGT_TICKET_GROUP_MANAGER, __('Technician in charge of the ticket'));
         $target->addTarget(self::NTRGT_TASK_GROUP,           __('Group in charge of the task'));
      }
   }

   static function getActionTargets(NotificationTarget $target) {
      if ($target instanceof NotificationTargetPlanningRecall) {
         $item = new $target->obj->fields['itemtype'];
         $item->getFromDB($target->obj->fields['items_id']);
         if ($item instanceof TicketTask) {

            $ticket = new Ticket;
            $ticket->getFromDB($item->getField('tickets_id'));

            switch ($target->data['type']) {
               case self::NTRGT_TICKET_GROUP:
                  $manager = 0;
               case self::NTRGT_TICKET_GROUP_MANAGER:
                  if (isset($manager)) {
                     $manager = 1;
                  }
                  $group_ticket = new Group_Ticket;
                  foreach($group_ticket->find("`tickets_id` = ".$ticket->getID()) as $current) {
                     $target->getAddressesByGroup($manager, $current['groups_id']);
                  }
                  break;
               case self::NTRGT_WATCHERS:
                  $user_type = CommonITILActor::OBSERVER;
               case self::NTRGT_TICKET_TECHNICIAN:
                  if (isset($user_type)) {
                     $user_type = CommonITILActor::ASSIGN;
                  }
                  $ticket_user = new Ticket_User;
                  $user        = new User;
                  foreach($ticket_user->find("`type` = $user_type
                                              AND `tickets_id` = ".$ticket->getID()) as $current) {
                     if ($user->getFromDB($ticket_user['users_id'])) {
                        $target->addToAddressesList(['language' => $user->getField('language'),
                                                     'users_id' => $user->getField('id')]);
                     }
                  }

                  break;
               case self::NTRGT_TASK_GROUP:
                  $target->getUserByField('groups_id_tech', true);
                  break;
            }
         }
      }
   }
}