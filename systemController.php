<?php

namespace App\Http\Controllers;
use Storage;
use Hash;
use Illuminate\Http\Request;
use App\Http\Requests;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Encryption\DecryptException;
use Mockery\CountValidator\Exception;
use Illuminate\Support\Facades\Auth;
use App\DataController;
use App\Examiner;
use App\Presentation;
use App\Report;
use App\User;
use App\ScientificQuality;
use App\ThesisPresentation;
ob_start();
session_start();
class systemController extends Controller
{
	
	 public function __construct()
    {
        $this->middleware('auth');
    }
    
	public function logout (Request $request)
    {
		 session_destroy();
         Auth::logout();
         return redirect('login');
	}
	public function getExaminerInfo (Request $request)
    {
		$user=User::where('id',$_SESSION['examiner'])->get();
		return $user;
	}
	public function getAdminInfo (Request $request)
    {
		$user=User::where('id',$_SESSION['admin'])->get();
		return $user;
	}
	public function addThesisReport(Request $request)
    {
		$examinerId=$request->input('examinerId');
		$thesisReport=$request->input('thesisReport');
		$thesisReport = json_decode($thesisReport,true);
		$examinerId = json_decode($examinerId,true);
		$date = date("Y-m-d", strtotime($thesisReport['date'].'+1 day'));
		$thesisReport['date']=$date;
		$report=Report::create($thesisReport);
		for($x=0;$x<count($examinerId);$x++)
		DataController::create(['reportId'=>$report['id'],'examinerId'=>$examinerId[$x],'status'=>'Pending','lastEvaluationIndex'=>0]);
		return 'done';
	}
	public function addExaminer(Request $request)
    {
		$examiner=$request->input('examiner');
		$examiner=json_decode($examiner,true);
		
		$user['name']=$examiner['name'];
		$user['username']=$examiner['userName'];
		$user['type']='examiner';
		
		$check=Examiner::where('username',$examiner['userName'])->get();
		if(count($check)==0)
		{
		    $examiner['password']=Hash::make($examiner['password']);
			$user['password']=$examiner['password'];
			User::create($user);
			$final = Examiner::create($examiner);
			return 'done';
		}
		return 'already exists';
		
	}
	
	public function getExaminers(Request $request)
    {
		$examiners = User::where('type','examiner')->get();
		return $examiners;
	}
	
	public function getThesisesPending(Request $request)
    {
		$data = DB::table('reports')
            	    ->join('datacontrollers', 'reports.id', '=', 'datacontrollers.reportId')
					->select('datacontrollers.id as ctrlId','reports.id as id','reports.studentName','reports.title','reports.studentId','reports.date','reports.comments')
					->where('datacontrollers.examinerId',$_SESSION['examiner'])
					->where('reports.deleted_at',null)
					->where('datacontrollers.status','Pending')
					->distinct()
					->get();
				$data=json_encode($data,true);
				$data=json_decode($data,true);	
				return $data;
	}
	public function getThesisesInProgress(Request $request)
    {
		$data = DB::table('reports')
            	    ->join('datacontrollers', 'reports.id', '=', 'datacontrollers.reportId')
					->select('datacontrollers.id as ctrlId','reports.id as id','reports.studentName','reports.title','reports.studentId','reports.date','reports.comments')
					->where('reports.deleted_at',null)
					->where('datacontrollers.status','InProgress')
					->distinct()
					->get();
		    $data=json_encode($data,true);
			$data=json_decode($data,true);	
		return $data;
	}
	
	public function getThesisesDone(Request $request)
    {
		$data = DB::table('reports')
            	    ->join('datacontrollers', 'reports.id', '=', 'datacontrollers.reportId')
            	    ->join('users', 'users.id', '=', 'datacontrollers.examinerId')
					->select('datacontrollers.id as ctrlId','reports.id as id','reports.studentName','reports.title','reports.studentId','reports.date','reports.comments','users.name')
					->where('reports.deleted_at',null)
					->where('datacontrollers.status','Completed')
                    ->where('datacontrollers.examinerId','=',$_SESSION['examiner'])
                    
                    ->distinct()
					->get();
		    $data=json_encode($data,true);
			$data=json_decode($data,true);	
		return $data;
	}
    public function getThesisesDoneForAdmin(Request $request)
    {
		$data = DB::table('reports')
            	    ->join('datacontrollers', 'reports.id', '=', 'datacontrollers.reportId')
            	    ->join('users', 'users.id', '=', 'datacontrollers.examinerId')
					->select('datacontrollers.id as ctrlId','reports.id as id','reports.studentName','reports.title','reports.studentId','reports.date','reports.comments','users.name')
					->where('reports.deleted_at',null)
					->where('datacontrollers.status','Completed')
                    
                    ->distinct()
					->get();
		    $data=json_encode($data,true);
			$data=json_decode($data,true);	
		return $data;
	}
	
	public function getThesisesTrash(Request $request)
    {
		$data = DB::table('reports')
            	    ->join('datacontrollers', 'reports.id', '=', 'datacontrollers.reportId')
            	    ->join('users', 'users.id', '=', 'datacontrollers.examinerId')
					->select('datacontrollers.id as ctrlId','reports.id as id','reports.studentName','reports.title','reports.studentId','reports.date','reports.comments','users.name')
					->where('reports.deleted_at',null)
					->where('datacontrollers.status','Closed')
					->distinct()
					->get();
		    $data=json_encode($data,true);
			$data=json_decode($data,true);	
		return $data;
	}
	public function createReportBasics(Request $request)
    {
		$id=$request->input('id');
        //return $id;
		$tp=ThesisPresentation::create();
		$p=Presentation::create();
		$sq=ScientificQuality::create();
		DataController::where('id',$id)
		 ->update([
			'scientificQualityId'=>$sq['id'],
			'presentationId'=>$p['id'],
			'thesisPresentationId'=>$tp['id'],
			'status'=>'InProgress',
		]);
		
		return 'done';
	}
	
    public function getThesisesData(Request $request)
    {
		$id=$request->input('id');
        
		$data = DB::table('reports')
            	    ->join('datacontrollers', 'reports.id', '=', 'datacontrollers.reportId')
            	    ->join('scientificqualitys', 'scientificqualitys.id', '=', 'datacontrollers.scientificQualityId')
            	    ->join('thesispresentations', 'thesispresentations.id', '=', 'datacontrollers.presentationId')
            	    ->join('presentations', 'presentations.id', '=', 'datacontrollers.thesisPresentationId')
            	    ->join('users', 'users.id', '=', 'datacontrollers.examinerId')
					//->where('datacontrollers.examinerId',$_SESSION['examiner'])
					->where('datacontrollers.id',$id)
					->where('reports.deleted_at',null)
					->distinct()
					->get();
		$data=json_encode($data,true);
		$data=json_decode($data,true);	
        return $data;
	}
	public function submitEvaluation(Request $request)
    {
		$data=$request->input('data');
		$id=$request->input('id');
		$indicator=$request->input('indicator');
		$data=json_decode($data,true);
		$data=$data['questions'];
		$data2=$data;
		$ids = DataController::where('id',$id)->get();
		$TP=$ids[0]['thesisPresentationId'];
		$SQ=$ids[0]['scientificQualityId'];
		$p=$ids[0]['presentationId'];
		/* calculations */
		$thesisPresentationGrade=0;
		$scientificQualityGrade=0;
		$presentationGrade=0;
		$questions=[];
		$grades=[];
		/*end*/
		if($indicator=='save')
		{
			$x=$request->input('x');
			DataController::where('id',$id)
			->update(['lastEvaluationIndex'=>$x]);
		}
    for($x=0;$x<count($data);$x++)
    {
		if($data[$x]['type']=='Thesis presentation')
		{
				
				$col = str_replace(' ', '', $data[$x]['title']);
				$col=$col.''.$data[$x]['Serialcounter'];
				$col= lcfirst($col);	
				Thesispresentation::where('id',$TP)
					->update([
					$col => $data[$x]['answer'],
				]);
			if($indicator=='submit')
			{				
				$data[$x]['title']=str_replace(' ', '', $data[$x]['title']);
				if(empty($questions['thesisPresentationTitles']))
					$questions['thesisPresentationTitles']=1;
				else if($data[$x]['Serialcounter']=='' || $data[$x]['Serialcounter']==1)
					$questions['thesisPresentationTitles']++;
				if($data[$x]['title']=='Title')
				{
					if(empty($questions[$data[$x]['title']]))
						$questions[$data[$x]['title']]=1;
					else 
						$questions[$data[$x]['title']]++;
				}
				else if($data[$x]['title']=='Abstract')
				{
					if(empty($questions[$data[$x]['title']]))
						$questions[$data[$x]['title']]=1;
					else 
						$questions[$data[$x]['title']]++;
				}
				else if($data[$x]['title']=='References')
				{
					if(empty($questions[$data[$x]['title']]))
						$questions[$data[$x]['title']]=1;
					else 
						$questions[$data[$x]['title']]++;
				}
				else if($data[$x]['title']=='Structure')
				{
					if(empty($questions[$data[$x]['title']]))
						$questions[$data[$x]['title']]=1;
					else 
						$questions[$data[$x]['title']]++;
				}
				else if($data[$x]['title']=='Length')
				{
					if(empty($questions[$data[$x]['title']]))
						$questions[$data[$x]['title']]=1;
					else 
						$questions[$data[$x]['title']]++;
				}
				else if($data[$x]['title']=='English')
				{
					if(empty($questions[$data[$x]['title']]))
						$questions[$data[$x]['title']]=1;
					else 
						$questions[$data[$x]['title']]++;
				}
				else if($data[$x]['title']=='Logic')
				{
					if(empty($questions[$data[$x]['title']]))
						$questions[$data[$x]['title']]=1;
					else 
						$questions[$data[$x]['title']]++;
				}
				else if($data[$x]['title']=='Figuresandtables')
				{
					if(empty($questions[$data[$x]['title']]))
						$questions[$data[$x]['title']]=1;
					else 
						$questions[$data[$x]['title']]++;
				}
			}
		}
		
		else if($data[$x]['type']=='Presentation')
		{
				$col = str_replace(' ', '', $data[$x]['title']);
				$col=$col.''.$data[$x]['Serialcounter'];
				$col= lcfirst($col);
				Presentation::where('id',$p)
					->update([
					$col => $data[$x]['answer'],
				]);
			if($indicator=='submit')
			{	
				$data[$x]['title']=str_replace(' ', '', $data[$x]['title']);
				if(empty($questions['presentationTitles']))
					$questions['presentationTitles']=1;
				else if($data[$x]['Serialcounter']=='' || $data[$x]['Serialcounter']==1)
					$questions['presentationTitles']++;
				if($data[$x]['title']=='Focus')
				{
					if(empty($questions[$data[$x]['title']]))
						$questions[$data[$x]['title']]=1;
					else 
						$questions[$data[$x]['title']]++;
				}
				else if($data[$x]['title']=='Organization')
				{
					if(empty($questions[$data[$x]['title']]))
						$questions[$data[$x]['title']]=1;
					else 
						$questions[$data[$x]['title']]++;
				}
				else if($data[$x]['title']=='Supportandelaboration')
				{
					if(empty($questions[$data[$x]['title']]))
						$questions[$data[$x]['title']]=1;
					else 
						$questions[$data[$x]['title']]++;
				}
				else if($data[$x]['title']=='Style')
				{
					if(empty($questions[$data[$x]['title']]))
						$questions[$data[$x]['title']]=1;
					else 
						$questions[$data[$x]['title']]++;
				}
				else if($data[$x]['title']=='Presentationskills')
				{
					if(empty($questions[$data[$x]['title']]))
						$questions[$data[$x]['title']]=1;
					else 
						$questions[$data[$x]['title']]++;
				}
				
			}
		}
		else if($data[$x]['type']=='Scientific Quality')
		{
				$col = str_replace(' ', '', $data[$x]['title']);
				$col=$col.''.$data[$x]['Serialcounter'];
				$col= lcfirst($col);
				Scientificquality::where('id',$SQ)
					->update([
					$col => $data[$x]['answer'],
				]);
			if($indicator=='submit')
			{	
				$data[$x]['title']=str_replace(' ', '', $data[$x]['title']);
				if(empty($questions['scientificQualityTitles']))
					$questions['scientificQualityTitles']=1;
				else if($data[$x]['Serialcounter']=='' || $data[$x]['Serialcounter']==1 )
					$questions['scientificQualityTitles']++;
				if($data[$x]['title']=='Originality')
				{
					if(empty($questions[$data[$x]['title']]))
						$questions[$data[$x]['title']]=1;
					else 
						$questions[$data[$x]['title']]++;
				}
				else if($data[$x]['title']=='Importanceandimpact')
				{
					if(empty($questions[$data[$x]['title']]))
						$questions[$data[$x]['title']]=1;
					else 
						$questions[$data[$x]['title']]++;
				}
				else if($data[$x]['title']=='Relevancetocomputer')
				{
					if(empty($questions[$data[$x]['title']]))
						$questions[$data[$x]['title']]=1;
					else 
						$questions[$data[$x]['title']]++;
				}
				else if($data[$x]['title']=='Completenessofpresentation')
				{
					if(empty($questions[$data[$x]['title']]))
						$questions[$data[$x]['title']]=1;
					else 
						$questions[$data[$x]['title']]++;
				}
				else if($data[$x]['title']=='Relevancetocomputer')
				{
					if(empty($questions[$data[$x]['title']]))
						$questions[$data[$x]['title']]=1;
					else 
						$questions[$data[$x]['title']]++;
				}
				else if($data[$x]['title']=='Completenessofpresentation')
				{
					if(empty($questions[$data[$x]['title']]))
						$questions[$data[$x]['title']]=1;
					else 
						$questions[$data[$x]['title']]++;
				}
			}
		}
	}
	if($indicator=='submit')
	{
		$thesisPresentationTitles=100/$questions['thesisPresentationTitles'];
		$scientificQualityTitles=100/$questions['scientificQualityTitles'];
		$presentationTitles=100/$questions['presentationTitles'];
		for($x=0;$x<count($data);$x++)
		{
			$questionGrade=0;
			if(empty($grade[$data[$x]['title']]))
			{
				$grade[$data[$x]['title'].'Avg']=0;
				$grade[$data[$x]['title']]=0;
			}
				
			
			if($data[$x]['type']=='Thesis presentation')
			{
				if($data[$x]['title']=='Title')
				{
					$questionGrade=$thesisPresentationTitles/$questions[$data[$x]['title']];
					$data[$x]['title'] = str_replace(' ', '', $data[$x]['title']);
					if($data[$x]['answer']==1)
						$grade[$data[$x]['title']]+=$questionGrade;//round up maybe always or according to rule
					else if($data[$x]['answer']==2)
						$grade[$data[$x]['title']]+=$questionGrade*0.75;
					else if($data[$x]['answer']==3)
						$grade[$data[$x]['title']]+=$questionGrade*0.5;
					else if($data[$x]['answer']==4)
						$grade[$data[$x]['title']]+=$questionGrade*0.25;
					 else if($data[$x]['answer']==5)
						$grade[$data[$x]['title']]+=$questionGrade*0; 
					$grade[$data[$x]['title'].'EquivalentGrade']=round(($grade[$data[$x]['title']]*100/$thesisPresentationTitles),1);
					$grade[$data[$x]['title'].'Avg']+=$data[$x]['answer'];
					$grade[$data[$x]['title'].'Points']=$grade[$data[$x]['title'].'Avg']/$questions[$data[$x]['title']];
				}
				
				else if($data[$x]['title']=='Abstract')
				{
					$questionGrade=$thesisPresentationTitles/$questions[$data[$x]['title']];
					$data[$x]['title'] = str_replace(' ', '', $data[$x]['title']);
					if($data[$x]['answer']==1)
						$grade[$data[$x]['title']]+=$questionGrade;//round up maybe always or according to rule
					else if($data[$x]['answer']==2)
						$grade[$data[$x]['title']]+=$questionGrade*0.75;
					else if($data[$x]['answer']==3)
						$grade[$data[$x]['title']]+=$questionGrade*0.5;
					else if($data[$x]['answer']==4)
						$grade[$data[$x]['title']]+=$questionGrade*0.25;
					 else if($data[$x]['answer']==5)
						$grade[$data[$x]['title']]+=$questionGrade*0; 
					$grade[$data[$x]['title'].'EquivalentGrade']=round(($grade[$data[$x]['title']]*100/$thesisPresentationTitles),1);
					$grade[$data[$x]['title'].'Avg']+=$data[$x]['answer'];
					$grade[$data[$x]['title'].'Points']=$grade[$data[$x]['title'].'Avg']/$questions[$data[$x]['title']];
				}
				else if($data[$x]['title']=='References')
				{
					$questionGrade=$thesisPresentationTitles/$questions[$data[$x]['title']];
					$data[$x]['title'] = str_replace(' ', '', $data[$x]['title']);
					if($data[$x]['answer']==1)
						$grade[$data[$x]['title']]+=$questionGrade;//round up maybe always or according to rule
					else if($data[$x]['answer']==2)
						$grade[$data[$x]['title']]+=$questionGrade*0.75;
					else if($data[$x]['answer']==3)
						$grade[$data[$x]['title']]+=$questionGrade*0.5;
					else if($data[$x]['answer']==4)
						$grade[$data[$x]['title']]+=$questionGrade*0.25;
					 else if($data[$x]['answer']==5)
						$grade[$data[$x]['title']]+=$questionGrade*0; 
					$grade[$data[$x]['title'].'EquivalentGrade']=round(($grade[$data[$x]['title']]*100/$thesisPresentationTitles),1);
					$grade[$data[$x]['title'].'Avg']+=$data[$x]['answer'];
					$grade[$data[$x]['title'].'Points']=$grade[$data[$x]['title'].'Avg']/$questions[$data[$x]['title']];
				}
				else if($data[$x]['title']=='Structure')
				{
					$questionGrade=$thesisPresentationTitles/$questions[$data[$x]['title']];
					$data[$x]['title'] = str_replace(' ', '', $data[$x]['title']);
					if($data[$x]['answer']==1)
						$grade[$data[$x]['title']]+=$questionGrade;//round up maybe always or according to rule
					else if($data[$x]['answer']==2)
						$grade[$data[$x]['title']]+=$questionGrade*0.75;
					else if($data[$x]['answer']==3)
						$grade[$data[$x]['title']]+=$questionGrade*0.5;
					else if($data[$x]['answer']==4)
						$grade[$data[$x]['title']]+=$questionGrade*0.25;
					 else if($data[$x]['answer']==5)
						$grade[$data[$x]['title']]+=$questionGrade*0; 
					$grade[$data[$x]['title'].'EquivalentGrade']=round(($grade[$data[$x]['title']]*100/$thesisPresentationTitles),1);
				  $grade[$data[$x]['title'].'Avg']+=$data[$x]['answer'];
					$grade[$data[$x]['title'].'Points']=$grade[$data[$x]['title'].'Avg']/$questions[$data[$x]['title']];
				}
				else if($data[$x]['title']=='Length')
				{
					$questionGrade=$thesisPresentationTitles/$questions[$data[$x]['title']];
					$data[$x]['title'] = str_replace(' ', '', $data[$x]['title']);
					if($data[$x]['answer']==1)
						$grade[$data[$x]['title']]+=$questionGrade;//round up maybe always or according to rule
					else if($data[$x]['answer']==2)
						$grade[$data[$x]['title']]+=$questionGrade*0.75;
					else if($data[$x]['answer']==3)
						$grade[$data[$x]['title']]+=$questionGrade*0.5;
					else if($data[$x]['answer']==4)
						$grade[$data[$x]['title']]+=$questionGrade*0.25;
					 else if($data[$x]['answer']==5)
						$grade[$data[$x]['title']]+=$questionGrade*0; 
					$grade[$data[$x]['title'].'EquivalentGrade']=round(($grade[$data[$x]['title']]*100/$thesisPresentationTitles),1);
			     $grade[$data[$x]['title'].'Avg']+=$data[$x]['answer'];
					$grade[$data[$x]['title'].'Points']=$grade[$data[$x]['title'].'Avg']/$questions[$data[$x]['title']];
				}
				else if($data[$x]['title']=='English')
				{
					$questionGrade=$thesisPresentationTitles/$questions[$data[$x]['title']];
					$data[$x]['title'] = str_replace(' ', '', $data[$x]['title']);
					if($data[$x]['answer']==1)
						$grade[$data[$x]['title']]+=$questionGrade;//round up maybe always or according to rule
					else if($data[$x]['answer']==2)
						$grade[$data[$x]['title']]+=$questionGrade*0.75;
					else if($data[$x]['answer']==3)
						$grade[$data[$x]['title']]+=$questionGrade*0.5;
					else if($data[$x]['answer']==4)
						$grade[$data[$x]['title']]+=$questionGrade*0.25;
					 else if($data[$x]['answer']==5)
						$grade[$data[$x]['title']]+=$questionGrade*0; 
					$grade[$data[$x]['title'].'EquivalentGrade']=round(($grade[$data[$x]['title']]*100/$thesisPresentationTitles),1);
				    $grade[$data[$x]['title'].'Avg']+=$data[$x]['answer'];
					$grade[$data[$x]['title'].'Points']=$grade[$data[$x]['title'].'Avg']/$questions[$data[$x]['title']];
				}
				else if($data[$x]['title']=='Logic')
				{
					$questionGrade=$thesisPresentationTitles/$questions[$data[$x]['title']];
					$data[$x]['title'] = str_replace(' ', '', $data[$x]['title']);
					if($data[$x]['answer']==1)
						$grade[$data[$x]['title']]+=$questionGrade;//round up maybe always or according to rule
					else if($data[$x]['answer']==2)
						$grade[$data[$x]['title']]+=$questionGrade*0.75;
					else if($data[$x]['answer']==3)
						$grade[$data[$x]['title']]+=$questionGrade*0.5;
					else if($data[$x]['answer']==4)
						$grade[$data[$x]['title']]+=$questionGrade*0.25;
					 else if($data[$x]['answer']==5)
						$grade[$data[$x]['title']]+=$questionGrade*0; 
					$grade[$data[$x]['title'].'EquivalentGrade']=round(($grade[$data[$x]['title']]*100/$thesisPresentationTitles),1);
					$grade[$data[$x]['title'].'Avg']+=$data[$x]['answer'];
					$grade[$data[$x]['title'].'Points']=$grade[$data[$x]['title'].'Avg']/$questions[$data[$x]['title']];
				}
				else if($data[$x]['title']=='Figuresandtables')
				{
					$questionGrade=$thesisPresentationTitles/$questions[$data[$x]['title']];
					$data[$x]['title'] = str_replace(' ', '', $data[$x]['title']);
					if($data[$x]['answer']==1)
						$grade[$data[$x]['title']]+=$questionGrade;//round up maybe always or according to rule
					else if($data[$x]['answer']==2)
						$grade[$data[$x]['title']]+=$questionGrade*0.75;
					else if($data[$x]['answer']==3)
						$grade[$data[$x]['title']]+=$questionGrade*0.5;
					else if($data[$x]['answer']==4)
						$grade[$data[$x]['title']]+=$questionGrade*0.25;
					 else if($data[$x]['answer']==5)
						$grade[$data[$x]['title']]+=$questionGrade*0; 
					$grade[$data[$x]['title'].'EquivalentGrade']=round(($grade[$data[$x]['title']]*100/$thesisPresentationTitles),1);
					$grade[$data[$x]['title'].'Avg']+=$data[$x]['answer'];
					$grade[$data[$x]['title'].'Points']=$grade[$data[$x]['title'].'Avg']/$questions[$data[$x]['title']];
				}
			}
			else if($data[$x]['type']=='Presentation')
			{
				if($data[$x]['title']=='Focus')
				{
					$questionGrade=$presentationTitles/$questions[$data[$x]['title']];
					$data[$x]['title'] = str_replace(' ', '', $data[$x]['title']);
					if($data[$x]['answer']==1)
						$grade[$data[$x]['title']]+=$questionGrade;//round up maybe always or according to rule
					else if($data[$x]['answer']==2)
						$grade[$data[$x]['title']]+=$questionGrade*0.75;
					else if($data[$x]['answer']==3)
						$grade[$data[$x]['title']]+=$questionGrade*0.5;
					else if($data[$x]['answer']==4)
						$grade[$data[$x]['title']]+=$questionGrade*0.25;
					 else if($data[$x]['answer']==5)
						$grade[$data[$x]['title']]+=$questionGrade*0; 
					$grade[$data[$x]['title'].'EquivalentGrade']=round(($grade[$data[$x]['title']]*100/$presentationTitles),1);
					$grade[$data[$x]['title'].'Avg']+=$data[$x]['answer'];
					$grade[$data[$x]['title'].'Points']=$grade[$data[$x]['title'].'Avg']/$questions[$data[$x]['title']];
				}
				else if($data[$x]['title']=='Organization')
				{
					$questionGrade=$presentationTitles/$questions[$data[$x]['title']];
					$data[$x]['title'] = str_replace(' ', '', $data[$x]['title']);
					if($data[$x]['answer']==1)
						$grade[$data[$x]['title']]+=$questionGrade;//round up maybe always or according to rule
					else if($data[$x]['answer']==2)
						$grade[$data[$x]['title']]+=$questionGrade*0.75;
					else if($data[$x]['answer']==3)
						$grade[$data[$x]['title']]+=$questionGrade*0.5;
					else if($data[$x]['answer']==4)
						$grade[$data[$x]['title']]+=$questionGrade*0.25;
					 else if($data[$x]['answer']==5)
						$grade[$data[$x]['title']]+=$questionGrade*0; 
					$grade[$data[$x]['title'].'EquivalentGrade']=round(($grade[$data[$x]['title']]*100/$presentationTitles),1);
					$grade[$data[$x]['title'].'Avg']+=$data[$x]['answer'];
					$grade[$data[$x]['title'].'Points']=$grade[$data[$x]['title'].'Avg']/$questions[$data[$x]['title']];
				}
				else if($data[$x]['title']=='Supportandelaboration')
				{
					$questionGrade=$presentationTitles/$questions[$data[$x]['title']];
					$data[$x]['title'] = str_replace(' ', '', $data[$x]['title']);
					if($data[$x]['answer']==1)
						$grade[$data[$x]['title']]+=$questionGrade;//round up maybe always or according to rule
					else if($data[$x]['answer']==2)
						$grade[$data[$x]['title']]+=$questionGrade*0.75;
					else if($data[$x]['answer']==3)
						$grade[$data[$x]['title']]+=$questionGrade*0.5;
					else if($data[$x]['answer']==4)
						$grade[$data[$x]['title']]+=$questionGrade*0.25;
					 else if($data[$x]['answer']==5)
						$grade[$data[$x]['title']]+=$questionGrade*0; 
					$grade[$data[$x]['title'].'EquivalentGrade']=round(($grade[$data[$x]['title']]*100/$presentationTitles),1);
					$grade[$data[$x]['title'].'Avg']+=$data[$x]['answer'];
					$grade[$data[$x]['title'].'Points']=$grade[$data[$x]['title'].'Avg']/$questions[$data[$x]['title']];
				}
				else if($data[$x]['title']=='Style')
				{
					$questionGrade=$presentationTitles/$questions[$data[$x]['title']];
					$data[$x]['title'] = str_replace(' ', '', $data[$x]['title']);
					if($data[$x]['answer']==1)
						$grade[$data[$x]['title']]+=$questionGrade;//round up maybe always or according to rule
					else if($data[$x]['answer']==2)
						$grade[$data[$x]['title']]+=$questionGrade*0.75;
					else if($data[$x]['answer']==3)
						$grade[$data[$x]['title']]+=$questionGrade*0.5;
					else if($data[$x]['answer']==4)
						$grade[$data[$x]['title']]+=$questionGrade*0.25;
					 else if($data[$x]['answer']==5)
						$grade[$data[$x]['title']]+=$questionGrade*0; 
					$grade[$data[$x]['title'].'EquivalentGrade']=round(($grade[$data[$x]['title']]*100/$presentationTitles),1);
					$grade[$data[$x]['title'].'Avg']+=$data[$x]['answer'];
					$grade[$data[$x]['title'].'Points']=$grade[$data[$x]['title'].'Avg']/$questions[$data[$x]['title']];
				}
				else if($data[$x]['title']=='Presentationskills')
				{
					$questionGrade=$presentationTitles/$questions[$data[$x]['title']];
					$data[$x]['title'] = str_replace(' ', '', $data[$x]['title']);
					if($data[$x]['answer']==1)
						$grade[$data[$x]['title']]+=$questionGrade;//round up maybe always or according to rule
					else if($data[$x]['answer']==2)
						$grade[$data[$x]['title']]+=$questionGrade*0.75;
					else if($data[$x]['answer']==3)
						$grade[$data[$x]['title']]+=$questionGrade*0.5;
					else if($data[$x]['answer']==4)
						$grade[$data[$x]['title']]+=$questionGrade*0.25;
					 else if($data[$x]['answer']==5)
						$grade[$data[$x]['title']]+=$questionGrade*0; 
					$grade[$data[$x]['title'].'EquivalentGrade']=round(($grade[$data[$x]['title']]*100/$presentationTitles),1);
					$grade[$data[$x]['title'].'Avg']+=$data[$x]['answer'];
					$grade[$data[$x]['title'].'Points']=$grade[$data[$x]['title'].'Avg']/$questions[$data[$x]['title']];
				}
				
			}	
			else if($data[$x]['type']=='Scientific Quality')
			{
				
				if($data[$x]['title']=='Originality')
				{
					$questionGrade=$scientificQualityTitles/$questions[$data[$x]['title']];
					$data[$x]['title'] = str_replace(' ', '', $data[$x]['title']);
					if($data[$x]['answer']==1)
						$grade[$data[$x]['title']]+=$questionGrade;//round up maybe always or according to rule
					else if($data[$x]['answer']==2)
						$grade[$data[$x]['title']]+=$questionGrade*0.75;
					else if($data[$x]['answer']==3)
						$grade[$data[$x]['title']]+=$questionGrade*0.5;
					else if($data[$x]['answer']==4)
						$grade[$data[$x]['title']]+=$questionGrade*0.25;
					 else if($data[$x]['answer']==5)
						$grade[$data[$x]['title']]+=$questionGrade*0; 
					$grade[$data[$x]['title'].'EquivalentGrade']=round(($grade[$data[$x]['title']]*100/$scientificQualityTitles),1);
					$grade[$data[$x]['title'].'Avg']+=$data[$x]['answer'];
					$grade[$data[$x]['title'].'Points']=$grade[$data[$x]['title'].'Avg']/$questions[$data[$x]['title']];
				}
				else if($data[$x]['title']=='Importanceandimpact')
				{
					$questionGrade=$scientificQualityTitles/$questions[$data[$x]['title']];
					$data[$x]['title'] = str_replace(' ', '', $data[$x]['title']);
					if($data[$x]['answer']==1)
						$grade[$data[$x]['title']]+=$questionGrade;//round up maybe always or according to rule
					else if($data[$x]['answer']==2)
						$grade[$data[$x]['title']]+=$questionGrade*0.75;
					else if($data[$x]['answer']==3)
						$grade[$data[$x]['title']]+=$questionGrade*0.5;
					else if($data[$x]['answer']==4)
						$grade[$data[$x]['title']]+=$questionGrade*0.25;
					 else if($data[$x]['answer']==5)
						$grade[$data[$x]['title']]+=$questionGrade*0; 
					$grade[$data[$x]['title'].'EquivalentGrade']=round(($grade[$data[$x]['title']]*100/$scientificQualityTitles),1);
					$grade[$data[$x]['title'].'Avg']+=$data[$x]['answer'];
					$grade[$data[$x]['title'].'Points']=$grade[$data[$x]['title'].'Avg']/$questions[$data[$x]['title']];
				}
				else if($data[$x]['title']=='Relevancetocomputer')
				{
					$questionGrade=$scientificQualityTitles/$questions[$data[$x]['title']];
					$data[$x]['title'] = str_replace(' ', '', $data[$x]['title']);
					if($data[$x]['answer']==1)
						$grade[$data[$x]['title']]+=$questionGrade;//round up maybe always or according to rule
					else if($data[$x]['answer']==2)
						$grade[$data[$x]['title']]+=$questionGrade*0.75;
					else if($data[$x]['answer']==3)
						$grade[$data[$x]['title']]+=$questionGrade*0.5;
					else if($data[$x]['answer']==4)
						$grade[$data[$x]['title']]+=$questionGrade*0.25;
					 else if($data[$x]['answer']==5)
						$grade[$data[$x]['title']]+=$questionGrade*0; 
					$grade[$data[$x]['title'].'EquivalentGrade']=round(($grade[$data[$x]['title']]*100/$scientificQualityTitles),1);
					$grade[$data[$x]['title'].'Avg']+=$data[$x]['answer'];
					$grade[$data[$x]['title'].'Points']=$grade[$data[$x]['title'].'Avg']/$questions[$data[$x]['title']];
				}
				else if($data[$x]['title']=='Completenessofpresentation')
				{
					$questionGrade=$scientificQualityTitles/$questions[$data[$x]['title']];
					$data[$x]['title'] = str_replace(' ', '', $data[$x]['title']);
					if($data[$x]['answer']==1)
						$grade[$data[$x]['title']]+=$questionGrade;//round up maybe always or according to rule
					else if($data[$x]['answer']==2)
						$grade[$data[$x]['title']]+=$questionGrade*0.75;
					else if($data[$x]['answer']==3)
						$grade[$data[$x]['title']]+=$questionGrade*0.5;
					else if($data[$x]['answer']==4)
						$grade[$data[$x]['title']]+=$questionGrade*0.25;
					 else if($data[$x]['answer']==5)
						$grade[$data[$x]['title']]+=$questionGrade*0; 
					$grade[$data[$x]['title'].'EquivalentGrade']=round(($grade[$data[$x]['title']]*100/$scientificQualityTitles),1);
					$grade[$data[$x]['title'].'Avg']+=$data[$x]['answer'];
					$grade[$data[$x]['title'].'Points']=$grade[$data[$x]['title'].'Avg']/$questions[$data[$x]['title']];
				}
			}
		}
		$grade['TitleGrade']=round($grade['Title'],1);
		$grade['AbstractGrade']=round($grade['Abstract'],1);
		$grade['CompletenessOfPresentationGrade']=round($grade['Completenessofpresentation'],1);
		$grade['EnglishGrade']=round($grade['English'],1);
		$grade['FiguresAndTablesGrade']=round($grade['Figuresandtables'],1);
		$grade['FocusGrade']=round($grade['Focus'],1);
		$grade['ImportanceAndImpactGrade']=round($grade['Importanceandimpact'],1);
		$grade['LengthGrade']=round($grade['Length'],1);
		$grade['LogicGrade']=round($grade['Logic'],1);
		$grade['OrganizationGrade']=round($grade['Organization'],1);
		$grade['OriginalityGrade']=round($grade['Originality'],1);
		$grade['PresentationSkillsGrade']=round($grade['Presentationskills'],1);
		$grade['ReferencesGrade']=round($grade['References'],1);
		$grade['RelevanceToComputerGrade']=round($grade['Relevancetocomputer'],1);
		$grade['StructureGrade']=round($grade['Structure'],1);
		$grade['StyleGrade']=round($grade['Style'],1);
		$grade['SupportAndElaborationGrade']=round($grade['Supportandelaboration'],1);
		$grade['thesisPresentationTotal'] = round($grade['TitleGrade']+$grade['AbstractGrade']+$grade['ReferencesGrade']+$grade['StructureGrade']+$grade['LengthGrade']
		+$grade['EnglishGrade']+$grade['LogicGrade']+$grade['FiguresAndTablesGrade'],0);
		$grade['scientificQualityTotal'] = round($grade['OriginalityGrade']+$grade['ImportanceAndImpactGrade']+$grade['RelevanceToComputerGrade']
		+$grade['CompletenessOfPresentationGrade'],0);
		$grade['presentationTotal']=round($grade['FocusGrade']+$grade['OrganizationGrade']+$grade['SupportAndElaborationGrade']+$grade['StyleGrade']+$grade['PresentationSkillsGrade'],0);
	}
        
		if($indicator=='save')
		return 'saved';
		if($indicator=='submit')
		{
			Thesispresentation::where('id',$TP)
				->update([
				/*    'TitleGrade'=>$grade['TitleGrade'],
				   'AbstractGrade'=>$grade['AbstractGrade'],
				   'ReferencesGrade'=>$grade['ReferencesGrade'],
				   'StructureGrade'=>$grade['StructureGrade'],
				   'LengthGrade'=>$grade['LengthGrade'],
				   'EnglishGrade'=>$grade['EnglishGrade'],
				   'LogicGrade'=>$grade['LogicGrade'],
				   'FiguresAndTablesGrade'=>$grade['FiguresAndTablesGrade'], */
				   'TitleEquivalentGrade'=>$grade['TitleEquivalentGrade'],
				   'AbstractEquivalentGrade'=>$grade['AbstractEquivalentGrade'],
				   'ReferencesEquivalentGrade'=>$grade['ReferencesEquivalentGrade'],
				   'StructureEquivalentGrade'=>$grade['StructureEquivalentGrade'],
				   'LengthEquivalentGrade'=>$grade['LengthEquivalentGrade'],
				   'EnglishEquivalentGrade'=>$grade['EnglishEquivalentGrade'],
				   'LogicEquivalentGrade'=>$grade['LogicEquivalentGrade'],
				   'FiguresandtablesEquivalentGrade'=>$grade['FiguresandtablesEquivalentGrade'],
				   'TitlePoints'=>$grade['TitlePoints'],
				   'AbstractPoints'=>$grade['AbstractPoints'],
				   'ReferencesPoints'=>$grade['ReferencesPoints'],
				   'StructurePoints'=>$grade['StructurePoints'],
				   'LengthPoints'=>$grade['LengthPoints'],
				   'EnglishPoints'=>$grade['EnglishPoints'],
				   'LogicPoints'=>$grade['LogicPoints'],
				   'FiguresAndTablesPoints'=>$grade['FiguresandtablesPoints'],
				   'thesisPresentationTotal'=>$grade['thesisPresentationTotal'],
				]);
				
			Scientificquality::where('id',$SQ)
					->update([
				  /*  'CompletenessOfPresentationGrade'=>$grade['CompletenessOfPresentationGrade'],
				   'OriginalityGrade'=>$grade['OriginalityGrade'],
				   'ImportanceAndImpactGrade'=>$grade['ImportanceAndImpactGrade'],
				   'RelevanceToComputerGrade'=>$grade['RelevanceToComputerGrade'], */
				   'CompletenessofpresentationEquivalentGrade'=>$grade['CompletenessofpresentationEquivalentGrade'],
				   'OriginalityEquivalentGrade'=>$grade['OriginalityEquivalentGrade'],
				   'ImportanceandimpactEquivalentGrade'=>$grade['ImportanceandimpactEquivalentGrade'],
				   'RelevancetocomputerEquivalentGrade'=>$grade['RelevancetocomputerEquivalentGrade'],
				   'CompletenessOfPresentationPoints'=>$grade['CompletenessofpresentationPoints'],
				   'OriginalityPoints'=>$grade['OriginalityPoints'],
				   'ImportanceAndImpactPoints'=>$grade['ImportanceandimpactPoints'],
				   'RelevanceToComputerPoints'=>$grade['RelevancetocomputerPoints'],
				   'scientificQualityTotal'=>$grade['scientificQualityTotal'],
				]);	
			
				Presentation::where('id',$p)
					->update([
				  /*  'FocusGrade'=>$grade['FocusGrade'],
				   'OrganizationGrade'=>$grade['OrganizationGrade'],
				   'SupportAndElaborationGrade'=>$grade['SupportAndElaborationGrade'],
				   'StyleGrade'=>$grade['StyleGrade'],
				   'PresentationSkillsGrade'=>$grade['PresentationSkillsGrade'], */
				   'FocusEquivalentGrade'=>$grade['FocusEquivalentGrade'],
				   'OrganizationEquivalentGrade'=>$grade['OrganizationEquivalentGrade'],
				   'SupportandelaborationEquivalentGrade'=>$grade['SupportandelaborationEquivalentGrade'],
				   'StyleEquivalentGrade'=>$grade['StyleEquivalentGrade'],
				   'PresentationskillsEquivalentGrade'=>$grade['PresentationskillsEquivalentGrade'],
				   'FocusPoints'=>$grade['FocusPoints'],
				   'OrganizationPoints'=>$grade['OrganizationPoints'],
				   'SupportAndElaborationPoints'=>$grade['SupportandelaborationPoints'],
				   'StylePoints'=>$grade['StylePoints'],
				   'PresentationSkillsPoints'=>$grade['PresentationskillsPoints'],
				   'presentationTotal'=>$grade['presentationTotal'],
				]);
			DataController::where('id',$id)
			->update(['status'=>'Completed']);
			return 'submitted';
		}
	}
     public function close(Request $request)
    {
		$id=$request->input('id');
		DataController::where('id',$id)
			->update(['status'=>'Closed']);
			return 'closed';
	} 
	public function delete(Request $request)
    {
		$id=$request->input('id');
		DataController::where('id',$id)
			->update(['status'=>'Deleted']);
			return 'Deleted';
	}
	public function recover(Request $request)
    {
		$id=$request->input('id');
		DataController::where('id',$id)
			->update(['status'=>'Completed']);
			return 'Deleted';
	}
	public function thesisExaminers(Request $request)
    {
		$id=$request->input('id');
		$reportId = DataController::where('id',$id)
			->get();
			$data = DB::table('DataControllers')
            	   ->join('reports', 'reports.id', '=', 'datacontrollers.reportId')
            	    ->join('scientificqualitys', 'scientificqualitys.id', '=', 'datacontrollers.scientificQualityId')
            	    ->join('thesispresentations', 'thesispresentations.id', '=', 'datacontrollers.presentationId')
            	    ->join('presentations', 'presentations.id', '=', 'datacontrollers.thesisPresentationId')
            	    ->join('users', 'users.id', '=', 'datacontrollers.examinerId')
					//->where('datacontrollers.examinerId',$_SESSION['examiner'])
					->where('datacontrollers.reportId',$reportId[0]['reportId'])
					->where('reports.deleted_at',null)
					->distinct()
					->get();
		$data=json_encode($data,true);
		$data=json_decode($data,true);	
		return $data;
	}
	
}