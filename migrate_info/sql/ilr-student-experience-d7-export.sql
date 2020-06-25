-- Run on an up-to-date IRL D7 database and export as JSON (via TablePlus or similar).

select n.*
  , a.alias
  , l.link_title as menu_link_title, l.mlid as menu_link_id, l.plid as menu_link_parent_id
--  , group_concat(p.item_id) as paragraph_item_ids
--  , group_concat(p.bundle) as paragraph_bundles
  , group_concat(tp.field_text_paragraph_value separator '\n----------------------\n') as text_paragraph_values
  , count(p.item_id) as text_paragraph_count
  , fi.field_image_fid
from node n
inner join url_alias a on n.nid = replace(a.source, 'node/', '')
inner join menu_links l on a.source = l.link_path
inner join field_data_field_para_main_page_content pf on n.nid = pf.entity_id and n.vid = pf.revision_id
inner join paragraphs_item p on pf.field_para_main_page_content_value = p.item_id and pf.field_para_main_page_content_revision_id = p.revision_id
inner join field_data_field_text_paragraph tp on tp.entity_type = 'paragraphs_item' and tp.bundle = 'just_text' and p.item_id = tp.entity_id and p.revision_id = tp.revision_id
left join field_data_field_image as fi on n.nid = fi.entity_id and n.vid = fi.revision_id
where a.alias like 'student-experience%'
and n.type in ('basic_page', 'channel_page')
and l.menu_name = 'main-menu'
-- and p.bundle = 'just_text'
-- and n.status = 0
group by n.nid
order by n.nid
;