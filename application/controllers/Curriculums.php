<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class Curriculums extends CI_Capstone_Controller
{


        private $page_;
        private $limit;

        function __construct()
        {
                parent::__construct();
                $this->lang->load('ci_capstone/ci_educations');
                $this->load->model(array('Curriculum_model', 'Course_model'));
                $this->load->library('pagination');
                $this->load->helper(array('school', 'inflector'));
                /**
                 * @Contributor: Jinkee Po <pojinkee1@gmail.com>
                 *         
                 */
                /**
                 * pagination limit
                 */
                $this->limit = 10;

                /**
                 * get the page from url
                 * 
                 */
                $this->page_ = get_page_in_url();
                $this->breadcrumbs->unshift(2, lang('curriculum_label'), 'curriculums');
        }

        public function index()
        {


                $curriculum_obj = $this->Curriculum_model->
                        fields(array(
                            'curriculum_description',
                            'curriculum_effective_school_year',
                            'curriculum_status',
                            'curriculum_already_used',
                            'curriculum_id',
                            'created_at',
                            'updated_at'
                        ))->
                        with_course('fields:course_code')->
                        with_user_created('fields:first_name,last_name')->
                        with_user_updated('fields:first_name,last_name')->
                        limit($this->limit, $this->limit * $this->page_ - $this->limit)->
                        order_by('created_at', 'DESC')->
                        order_by('updated_at', 'DESC')->
                        set_cache('curriculum_page_' . $this->page_)->
                        get_all();


                $table_data = array();

                if ($curriculum_obj)
                {
                        foreach ($curriculum_obj as $curriculum)
                        {
                                $view = anchor(site_url('curriculums/view?curriculum-id=' . $curriculum->curriculum_id), '<button class="btn btn-mini">' . lang('curriculumn_view') . '</button>');

                                $tmp = array(
                                    my_htmlspecialchars($curriculum->course->course_code),
                                    my_htmlspecialchars($curriculum->curriculum_description),
                                    my_htmlspecialchars($curriculum->curriculum_effective_school_year),
                                    $this->_enable_button($curriculum->curriculum_status, $curriculum->curriculum_id, $curriculum->curriculum_already_used),
                                    $view
                                );
                                if ($this->ion_auth->is_admin())
                                {

                                        $tmp[] = $this->User_model->modidy($curriculum, 'created');
                                        $tmp[] = $this->User_model->modidy($curriculum, 'updated');
                                }
                                array_push($table_data, $tmp);
                        }
                }

                /*
                 * Table headers
                 */
                $header = array(
                    lang('curriculumn_course'),
                    lang('curriculumn_description'),
                    lang('curriculumn_effective_year'),
                    lang('curriculumn_status'),
                    lang('curriculumn_option')
                );

                if ($this->ion_auth->is_admin())
                {
                        $header[] = 'Created By';
                        $header[] = 'Updated By';
                }
                $pagination = $this->pagination->generate_bootstrap_link('curriculums/index', $this->Curriculum_model->count_rows() / $this->limit);

                $this->template['table_curriculm'] = $this->table_bootstrap($header, $table_data, 'table_open_bordered', 'curriculum_label', $pagination, TRUE);
                $this->template['message']         = (($this->ion_auth->errors() ? $this->ion_auth->errors() : $this->session->flashdata('message')));
                $this->template['bootstrap']       = $this->_bootstrap();
                /**
                 * rendering users view
                 */
                $this->render('admin/curriculums', $this->template);
        }

        /**
         * generate link for enabling curriculum,
         * if curriculum is enabled already, it will just return a status "Enabled"
         * 
         * note: enabling curriculum will disable other with same course_id and also even disabled it cannot edit/add data (because it already used by enrollment),
         * then a current curriculum cannot add/edit subjects
         * 
         * @param type $current_status
         * @return string
         * @author Lloric Mayuga Garcia <emorickfighter@gmail.com>
         */
        private function _enable_button($current_status, $id, $already_used)
        {
                $addtional_data = array('class' => 'taskStatus');
                if ($current_status)
                {
                        $addtional_data['data'] = '<span class="done">Enabled</span>';
                        return $addtional_data;
                }
//                if ($already_used)
//                {
//                        $addtional_data['data'] = '<span class="pending">Disabled</span>';
//                        return $addtional_data;
//                }
                $addtional_data['data'] = anchor(site_url('set-curriculum-enable?curriculum-id=' . $id), '<button class="btn btn-mini">' . lang('enable_curriculum_label') . '</button>');
                return $addtional_data;
        }

        private function _hour($hr)
        {
                $hr = (int) $hr;
                if ($hr === 0)
                {
                        return '--';
                }

                $unit = 'Hour';
                if ($hr > 1)
                {
                        $unit = plural($unit);
                }

                return $hr . ' ' . $unit;
        }

        public function view()
        {
                $curriculum_obj = check_id_from_url('curriculum_id', 'Curriculum_model', 'curriculum-id', 'course');
                $this->breadcrumbs->unshift(3, lang('curriculum_subject_label'), 'curriculums/view?curriculum-id=' . $curriculum_obj->curriculum_id);

                $this->load->model(array('Curriculum_subject_model', 'Subject_model', 'Requisites_model'));
                $this->load->helper(array('number', 'text', 'inflector'));
                $highlight_phrase = '';

                if ($h = $this->input->get('highlight'))
                {
                        $highlight_phrase = $h;
                }

                //$this->load->library('curriculum');
                $cur_subj_obj = $this->Curriculum_subject_model->curriculum_subjects($curriculum_obj->curriculum_id);
                //  print_r($cur_subj_obj);
                // $cur_subj_obj = $this->curriculum->get_subjects();

                $table_data = array();
                if ($cur_subj_obj)
                {
                        // print_r($cur_subj_obj);
                        $year         = 1;
                        $table_data[] = array(array('data' => '<h4>' . number_place($year) . ' Year</h4>', 'colspan' => '7'));
                        foreach ($cur_subj_obj as $cur_subj)
                        {
                                if ($year != $cur_subj->curriculum_subject_year_level)
                                {
                                        $table_data[] = array(array('data' => '<h4>' . number_place($cur_subj->curriculum_subject_year_level) . ' Year</h4>', 'colspan' => '8'));

                                        $year ++;
                                }
                                $requisite = $this->Requisites_model->subjects(isset($cur_subj->requisites) ? $cur_subj->requisites : NULL);
                                $tmp       = array(
                                    // my_htmlspecialchars($cur_subj->curriculum_subject_year_level),
                                    my_htmlspecialchars(semesters($cur_subj->curriculum_subject_semester)),
                                    highlight_phrase($cur_subj->subject->subject_code, $highlight_phrase, '<span class="badge badge-info" id="' . dash($cur_subj->subject->subject_code) . '">', '</span>'),
                                    my_htmlspecialchars($cur_subj->subject->subject_description),
                                    my_htmlspecialchars($cur_subj->curriculum_subject_units),
                                    my_htmlspecialchars($this->_hour($cur_subj->curriculum_subject_lecture_hours)),
                                    my_htmlspecialchars($this->_hour($cur_subj->curriculum_subject_laboratory_hours)),
                                    $requisite->pre,
                                    $requisite->co
                                );
                                if ( ! $curriculum_obj->curriculum_status && ! $curriculum_obj->curriculum_already_used)
                                {
                                        $tmp[] = anchor('create-requisite?curriculum-id=' . $curriculum_obj->curriculum_id . '&curriculum-subject-id=' . $cur_subj->curriculum_subject_id, '<button class="btn btn-mini pending">' . lang('create_requisite_label') . '</button>');
                                }
                                $table_data[] = $tmp;
                        }
                }
                /*
                 * Table headers
                 */
                $header = array(
                    //  lang('curriculum_subject_year_level_label'),
                    lang('curriculum_subject_semester_label'),
                    lang('curriculum_subject_subject_label'),
                    'desc',
                    lang('curriculum_subject_units_label'),
                    lang('curriculum_subject_lecture_hours_label'),
                    lang('curriculum_subject_laboratory_hours_label'),
                    lang('curriculum_subject_pre_subject_label'),
                    lang('curriculum_subject_co_subject_label')
                );

                if ( ! $curriculum_obj->curriculum_status && ! $curriculum_obj->curriculum_already_used)
                {
                        $header[] = 'add Requisite';

                        $this->template['create_curriculum_subject_button'] = MY_Controller::render('admin/_templates/button_view', array(
                                    'href'         => 'create-curriculum-subject?curriculum-id=' . $curriculum_obj->curriculum_id,
                                    'button_label' => lang('create_curriculum_subject_label'),
                                    'extra'        => array('class' => 'btn btn-success icon-edit'),
                                        ), TRUE);
                }

                $this->template['curriculum_information']    = MY_Controller::render('admin/_templates/curriculums/curriculum_information', array('curriculum_obj' => $curriculum_obj), TRUE);
                $this->template['curriculum_obj']            = $curriculum_obj;
                $this->template['table_corriculum_subjects'] = $this->table_bootstrap($header, $table_data, 'table_open_bordered', 'curriculum_subject_label', FALSE, TRUE);
                $this->template['message']                   = (($this->ion_auth->errors() ? $this->ion_auth->errors() : $this->session->flashdata('message')));
                $this->template['bootstrap']                 = $this->_bootstrap();
                $this->render('admin/curriculums', $this->template);
        }

        /**
         * 
         * @return array
         *  @author Lloric Garcia <emorickfighter@gmail.com>
         */
        private function _bootstrap()
        {
                /**
                 * for header
                 * 
                 */
                $header       = array(
                    'css' => array(
                        'css/bootstrap.min.css',
                        'css/bootstrap-responsive.min.css',
                        'css/uniform.css',
                        'css/select2.css',
                        'css/matrix-style.css',
                        'css/matrix-media.css',
                        'font-awesome/css/font-awesome.css',
                        'http://fonts.googleapis.com/css?family=Open+Sans:400,700,800',
                    ),
                    'js'  => array(
                    ),
                );
                /**
                 * for footer
                 * 
                 */
                $footer       = array(
                    'css' => array(
                    ),
                    'js'  => array(
                        'js/jquery.min.js',
                        'js/jquery.ui.custom.js',
                        'js/bootstrap.min.js',
                        'js/jquery.uniform.js',
                        'js/select2.min.js',
                        'js/jquery.dataTables.min.js',
                        'js/matrix.js',
                        'js/matrix.tables.js',
                    ),
                );
                /**
                 * footer extra
                 */
                $footer_extra = '';
                return generate_link_script_tag($header, $footer, $footer_extra);
        }

}
