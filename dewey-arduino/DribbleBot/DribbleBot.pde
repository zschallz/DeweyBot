// Sweep
// by BARRAGAN <http://barraganstudio.com> 

#include <Servo.h>
#include <WString.h>
#include <NewSoftSerial.h>
 
#define RECV_BUFFER 200

Servo myServRot;  // create servo object to control a servo, rotational 
Servo myServOther;//create servo object to control a servo, forward/backward 

NewSoftSerial softSerial(2, 3);
 
//int behavior = 0;  //integer for switch defining which behavior to perform, 
                  //99 reserved for blank state or "waiting."

void setup() 
{ 
  myServOther.attach(9);  // attaches the servo on pin 9 to the servo object 
  myServRot.attach(10); //attaches the servo on pin 10 to the servo object
  
  Serial.begin(9600);
  softSerial.begin(9600);
} 
 
 
 
void loop() 
{ 
  String command = readCommand();
  
  if( command.length() > 0 )
    executeCommand(command);
}


void executeCommand(String command)
{
  String trimmed = command.substring(1,command.length()-1);
  // add more cases here
  if( trimmed.equals("0") || trimmed.equals("1") || 
      trimmed.equals("2") || trimmed.equals("3") )
  {
    performBehavior( atoi(trimmed.getChars()) );    
  }
}


String response;

String readCommand()
{
  //while( softSerial.available() > 0 )
  while(Serial.available() > 0) //debug
  {
    //char c = softSerial.read();
    char c = Serial.read(); // debug
    
    /* Has the beginning of a command been detected? */
    if( c == '(' )
      response = String(RECV_BUFFER);
    
    response.append(c);
    
    /* Has the end of a command been detected? */
    if( c == ')' )
      return response;
  }

  return String(0);
}

// behavior = integer for switch defining which behavior to perform, 
// 99 reserved for blank state or "waiting."

void performBehavior(int behavior)
{
    int pos1 = 0;    // variable to store the servo position 
    int pos2 = 0;
  
  
    switch(behavior){
      case 0:  //shaking head slowly, LEVEL 1 ALARM
        Serial.println("DEBUG: executing behavior 0... *shakes head slowly*");
        for(int i = 0; i <= 2; i+=1){
		for(pos1 = 0; pos1 < 180; pos1 += 1)  // goes from 0 degrees to 180 degrees 
		{                                  // in steps of 4 degrees 
		  myServRot.write(pos1);              // tell servo to go to position in variable 'pos' 
		  delay(15);                       // waits 15ms for the servo to reach the position 
		} 
		for(pos1 = 180; pos1 >= 1; pos1 -= 1)     // goes from 180 degrees to 0 degrees 
		{                                  
		  myServRot.write(pos1);              // tell servo to go to position in variable 'pos' 
		  delay(15);                       // waits 15ms for the servo to reach the position 
		}
	}

	behavior = 4;	

        break;
       
       case 1: //rock side-side, HAPPY/GREETING
         Serial.println("DEBUG: executing behavior 1... *rocks side-to-side happily*");
         for(int i = 0; i <= 2; i+=1)
	 {
	  for(pos2 = 60; pos2 < 130; pos2 += 2)  
          {                                 
            myServOther.write(pos2);               
            delay(15);                        
          } 
          for(pos2 = 130; pos2 >= 60; pos2 -= 2)     
          {                                  
            myServOther.write(pos2);               
            delay(15);                       
          }
	 }
	 behavior = 4;
        break;
        
        case 2: //shakes head fast LEVEL 2 AGITATION
          Serial.println("DEBUG: executing behavior 2... *shakes head fast*");
          for(int i = 0; i <= 2; i+=2)
	  {
		  for(pos1 = 0; pos1 < 180; pos1 += 5)  
		  {                                  
		    myServRot.write(pos1);              
		    delay(15);                       
		  } 
		  for(pos1 = 180; pos1 >= 1; pos1 -= 5)     
		  {                                  
		    myServRot.write(pos1);               
		    delay(15);                       
		  }
	}
        break;
        
        case 3: //complete spaz: fast rocking and shaking head, LEVEL 3 PISSED OFF
         Serial.println("DEBUG: executing behavior 3: *spazzes out*"); 
         for(int i = 0; i <= 1; i +=1)
	 {
		  for(pos1 = 60; pos1 < 130; pos1 += 5)  
		  {                                  
		    myServRot.write(pos1);  //both servos change to the same position at the same rate
		    myServOther.write(pos1);          
		    delay(15);                       
		  } 
		  for(pos1 = 130; pos1 >= 60; pos1 -= 5)     
		  {                                  
		    myServRot.write(pos1);
		    myServOther.write(pos1);          
		    delay(15);                      
		  }
	}
         break;

        case 4: //robot laying down, resting position
         Serial.println("DEBUG:  executing behavior 4:  *laying down*");
         for(pos2 = 130; pos2 >= 20; pos2 -= 1)
         {
            myServOther.write(pos2); 
            delay(15);
         }
         
         case 99:  //blank case, robot sitting still
          Serial.println("DEBUG: executing behavior 99: *does nothing*");
         break;
  
  } 
}
