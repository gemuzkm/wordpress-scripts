Indexing embedded PDFs for the parent post

Relevanssi can automatically index PDF content for the parent post if the PDF (or other attachment) is attached to the parent post in WordPress. However, that’s not always the case. Sometimes the PDF is attached to the page using an embed, which doesn’t create a connection between the posts in WordPress. Thus, Relevanssi won’t know the PDF is embedded in the post and cannot index the PDF contents for the parent post.

Most of these plugins use shortcodes to embed the PDF viewer on a page. To get Relevanssi to index the embedded PDF contents for the parent post, you need to establish a connection between the PDF and the post, based on the URL in the shortcode.

The same code works with different PDF embedders; you only have to adjust the regex to match the shortcode used by the plugin.

PDF Embedder uses the same method, so the only change is the name of the shortcode: