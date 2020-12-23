<?php

namespace Drupal\ilr_salesforce;

/**
 * Course and topic definition based on data from a google spreadsheet.
 */
trait CourseToTopicsTrait {

  /**
   * The data captured from the google doc.
   *
   * This data is simply columns B, C, and D pasted from
   * https://docs.google.com/spreadsheets/d/1ClG7pik5TZt3RwCbh7UkP5Hj40raol3M1LfKxSvFdX4/edit.
   *
   * Note that tabs and spaces are preserved here, and they matter.
   *
   * @var string
   */
  private $courseToTopicsTsv = <<<'EOD'
LR353	Labor Relations	Conflict Resolution
LR354	Labor Relations	Human Resources
LR356	Labor Relations	Conflict Resolution
LR358	Labor Relations	Human Resources
LR105	Labor Relations	Human Resources
LR205	Labor Relations	Human Resources
LR201	Labor Relations	Human Resources
LR203	Labor Relations	Human Resources
LR311	Labor Relations	Conflict Resolution
LR312	Labor Relations	Conflict Resolution
LR101	Labor Relations	Human Resources
LR108	Labor Relations	Human Resources
LR107	Labor Relations	Human Resources
LR102	Labor Relations	Human Resources
CO111	Employment Law	Human Resources
CO231	Employee Relations	Human Resources
CO332	Employee Relations	Human Resources
CO240	Employee Relations	Human Resources
CO220	Employee Relations	Human Resources
CO336	Employee Relations	Conflict Resolution
LD363	Leadership Development and Organizational Change
CO100	Employment Law	Human Resources
CO213	Employment Law	Human Resources
DR146	Conflict Resolution	Human Resources
DR148	Conflict Resolution	Human Resources
DR210	Conflict Resolution	Diversity and Inclusion
DR152	Conflict Resolution	Employee Relations
DR170	Conflict Resolution	Labor Relations
DR110	Conflict Resolution	Employment Law
DR220	Conflict Resolution
CO338	Employment Law	Human Resources
LS253	Labor Relations	Leadership Development and Organizational Change
LS210	Labor Relations	Leadership Development and Organizational Change
LS214	Labor Relations	Leadership Development and Organizational Change
LS203	Labor Relations	Leadership Development and Organizational Change
LS249	Labor Relations	Leadership Development and Organizational Change
LB118	Labor Relations	Leadership Development and Organizational Change
LB116	Labor Relations	Leadership Development and Organizational Change
LB114	Labor Relations	Leadership Development and Organizational Change
LB112	Labor Relations	Leadership Development and Organizational Change
OLLB105	Labor Relations	Leadership Development and Organizational Change
LB105	Labor Relations	Leadership Development and Organizational Change
OLLB118	Labor Relations	Leadership Development and Organizational Change
OLLB116	Labor Relations	Leadership Development and Organizational Change
OLLB114	Labor Relations	Leadership Development and Organizational Change
OLLB112	Labor Relations	Leadership Development and Organizational Change
LS256	Labor Relations	Leadership Development and Organizational Change
LS252	Labor Relations	Leadership Development and Organizational Change
LS267	Labor Relations	Leadership Development and Organizational Change
LS263	Labor Relations	Leadership Development and Organizational Change
LS239	Labor Relations	Leadership Development and Organizational Change
LS138	Labor Relations	Leadership Development and Organizational Change
LS238	Labor Relations	Leadership Development and Organizational Change
LS116	Labor Relations	Leadership Development and Organizational Change
LS100	Labor Relations	Leadership Development and Organizational Change
LS200	Labor Relations	Leadership Development and Organizational Change
LS254	Labor Relations	Leadership Development and Organizational Change
LS255	Labor Relations	Leadership Development and Organizational Change
LS086	Labor Relations	Leadership Development and Organizational Change
LS248	Labor Relations	Leadership Development and Organizational Change
LB407	Labor Relations	Leadership Development and Organizational Change
LB603	Labor Relations	Leadership Development and Organizational Change
LB666	Labor Relations	Leadership Development and Organizational Change
OLLB110	Labor Relations	Leadership Development and Organizational Change
LS246	Labor Relations	Leadership Development and Organizational Change
LS117	Labor Relations	Leadership Development and Organizational Change
LS187	Labor Relations	Leadership Development and Organizational Change
LS184	Labor Relations	Leadership Development and Organizational Change
LS208	Labor Relations	Leadership Development and Organizational Change
DV227	Diversity and Inclusion	Human Resources
DV310	Diversity and Inclusion	Human Resources
LD305	Leadership Development and Organizational Change	Human Resources
DV350	Diversity and Inclusion	Human Resources
LD255	Leadership Development and Organizational Change	Diversity and Inclusion
LD320	Leadership Development and Organizational Change	Human Resources
DV247	Diversity and Inclusion	Human Resources
HR409	Human Resources
DV235	Diversity and Inclusion	Human Resources
DV120	Diversity and Inclusion	Human Resources
HR415	Human Resources
HR417	Human Resources
DV221	Diversity and Inclusion	Human Resources
DV320	Diversity and Inclusion	Human Resources
HR413	Human Resources
HR104	Human Resources
LD250	Leadership Development and Organizational Change
HR420	Human Resources
DV358	Diversity and Inclusion	Human Resources
LD314	Leadership Development and Organizational Change
EE500	Human Resources	Leadership Development and Organizational Change
EE505	Human Resources	Leadership Development and Organizational Change
EE510	Human Resources	Leadership Development and Organizational Change
EOD;

}
