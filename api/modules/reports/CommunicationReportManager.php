<?php
namespace App\API\Modules\reports;
use App\API\Includes\BaseAPI;

class CommunicationReportManager extends BaseAPI
{
    public function getCommunicationsStats($filters = [])
    {
        // Example: Count communications by type and status
        $sql = "SELECT type, status, COUNT(*) as total
                FROM communications
                GROUP BY type, status";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    public function getParentPortalStats($filters = [])
    {
        // Example: Count parent portal messages by status
        $sql = "SELECT status, COUNT(*) as total
                FROM parent_portal_messages
                GROUP BY status";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    public function getForumActivityStats($filters = [])
    {
        // Example: Count forum threads and posts by type
        $sql = "SELECT forum_type, COUNT(*) as thread_count
                FROM forum_threads
                GROUP BY forum_type";
        $stmt1 = $this->db->query($sql);
        $threads = $stmt1->fetchAll(\PDO::FETCH_ASSOC);
        $sql2 = "SELECT author_type, COUNT(*) as post_count
                 FROM forum_posts
                 GROUP BY author_type";
        $stmt2 = $this->db->query($sql2);
        $posts = $stmt2->fetchAll(\PDO::FETCH_ASSOC);
        return ['threads' => $threads, 'posts' => $posts];
    }
    public function getAnnouncementReach($filters = [])
    {
        // Example: Count announcements and their read status
        $sql = "SELECT a.id, a.title, COUNT(n.id) as total_recipients,
                       SUM(n.status = 'read') as read_count
                FROM announcements a
                LEFT JOIN notifications n ON n.announcement_id = a.id
                GROUP BY a.id, a.title";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
