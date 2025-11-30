add SQL index:

CREATE INDEX idx_post_title_status_type 
ON procar_posts (post_title(191), post_status, post_type);