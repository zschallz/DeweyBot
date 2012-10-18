#include <Ethernet.h>
#include <TimedAction.h>
#include <stdio.h>
#include <i2cmaster.h>
#include <WString.h>
#include <NewSoftSerial.h>

#define RECV_BUFFER 200

/* Hardware details for Dewey */
int motionSensorPin = 0;

/* Identity details for Dewey:
   To send data to the server, the server requires a participantID
   and a key to verify that participantID. The key is like a password
   and the participantID is like the username.
*/
int participantID   = 1;
char key[]          = "zschallz";

/* Network details for the Arduino */
byte mac[]       = { 0xDE, 0xAD, 0xBE, 0xEF, 0xFE, 0xED };
byte ip[]        = { 192, 168, 1, 250 };
byte gateway[]   = { 192, 168, 1, 1 };
byte subnet[]    = { 255, 255, 255, 0 };

/* Network details for the remote server */
// 173.79.13.162
byte server[]    = { 156, 56, 29, 141 }; // MUST be IP, not DNS; if the server does not have a dedicated ip you can define the host in the host variable
int port         = 80;                // 80 is normal port for HTTP
char host[]      = "dribble.ath.cx";      // can be the dns host

boolean expectingResponse = false;
boolean notificationOn = false;

/* Timers */
TimedAction pullTimer   = TimedAction(45000,pull); // allow pull() to execute every 30min
TimedAction sensorCheckTimer = TimedAction(5,sensorCheck); // allow sensorCheck() to execute every 50ms
TimedAction sendReportTimer = TimedAction(10000,sendReport);
TimedAction recvCheckTimer  = TimedAction(250,recvTimer); // allow recvTimer() to execute every 200ms

/* objects */
Client client(server,port);
NewSoftSerial mySerial(2, 3);

void setup()
{
  Ethernet.begin( mac, ip, gateway, subnet );
  Serial.begin(9600);
  
  diagSendStartEvent(); // Note: not guaranteed to be sent... problematic?
  initTempSensor();
  initMotionSensor();
}
void loop()
{
  pullTimer.check();
  sensorCheckTimer.check();
  sendReportTimer.check();
  recvCheckTimer.check();
}
void initTempSensor()
{
  Serial.print( "Initializing temperature sensor..." );
  i2c_init(); //Initialise the i2c bus 
  PORTC = (1 << PORTC4) | (1 << PORTC5); //enable pullups
  Serial.println( "done" );
}
void initMotionSensor()
{
  pinMode(motionSensorPin, INPUT);
  Serial.print( "Initializing motion sensor... (waiting 10 seconds)" );
  delay(10000);
  Serial.println( "done" );
}
/* Sensor Stuff */
boolean motionValue = false;
double tempValueTotal = 0;
int samples = 0;

void sensorCheck()
{
  double tempValue    = getTempSensorValue();
  
  samples++;
  tempValueTotal += tempValue;
  
  if(getMotionSensorValue())
    motionValue = true;
}
double getTempSensorValue()
{
  /* I2C Communications and converstions necessary to get temperature */
  /* http://www.arduino.cc/cgi-bin/yabb2/YaBB.pl?num=1214872633/15 */
  int dev = 0x5A<<1; 
  int data_low = 0; 
  int data_high = 0; 
  int pec = 0; 
  i2c_start_wait(dev+I2C_WRITE); 
  i2c_write(0x07); 
  
  i2c_rep_start(dev+I2C_READ);
  data_low = i2c_readAck(); //Read 1 byte and then send ack 
  data_high = i2c_readAck(); //Read 1 byte and then send ack 
  pec = i2c_readNak(); 
  i2c_stop(); 
  
  //This converts high and low bytes together and processes temperature, MSB is a error bit and is ignored for temps 
  double tempFactor = 0.02; // 0.02 degrees per LSB 
  double tempData = 0x0000; 
  
  // This masks off the error bit of the high byte, then moves it left 8 bits and adds the low byte. 
  tempData = (double)(((data_high & 0x007F) << 8) + data_low); 
  tempData = (tempData * tempFactor)-0.01; 
  tempData = tempData - 273.15; 
  
  return tempData;
}
boolean getMotionSensorValue()
{
  int value = analogRead(motionSensorPin);
  if(value < 100)
    return true;
  else
    return false;
}

/* Network IO Stuff */
/* Wrapper for client.print. Attempts to connect to the server and 
   if all is well posts to the script's get variables causing data
   to be stored in the database.
*/

/* This function takes the total of all temperature readings and averages it
 * based on the number of samples taken, sends it to the server along with
 * whether or not motion was ever detected during the samples and
 * resets tempDataTotal, samples, and motionValue.
 */
void sendReport()
{
  char toSend[128];
  
  // calculate average temperature
  double tempValueAvg = tempValueTotal / samples;
  
  /* arduino has a problem with decimals and sprintf.... so work around is below */
  int intPrecision = (int) tempValueAvg;
  int remainder = (tempValueAvg-(int)tempValueAvg)*100; // off by 1 because of precision, but whatever
  
  // if motion is ever detected
  if(motionValue)
    sprintf(toSend, "&com=sensor&temp=%d.%d&motion=true", intPrecision, remainder);
  else
    sprintf(toSend, "&com=sensor&temp=%d.%d&motion=false", intPrecision, remainder);
    
  sendToServer(toSend, true);
  
  // reset values
  tempValueTotal = 0;
  motionValue = false;
  samples = 0;
}
void sendToServer( char* toSend, boolean isPush )
{
  /* if we can connect, access push.php supplying get variables for
     participantID, key, and whatever is in toSend
     
     Does not validate whatever is in toSend, so it assumes whoever
     is using it knows what they are doing.
  */
  
  if( client.connect() )
  {
    debugPrint( " Connected to remote server." );
    if( isPush )
      client.print( "GET /push.php?pid=" );
    else
      client.print( "GET /pull.php?pid=" );
    client.print( participantID );
    client.print( "&key=" );
    client.print( key );
    client.print( toSend );
    client.print(" HTTP/1.1\n");
    client.print("Host: ");
    client.print(host);
    client.println("\nUser-Agent: Dewey/Arduino");
    client.println();
  }
  else
  {
    Serial.print( " Tried to send: " );
    Serial.println(toSend);
    Serial.println( " Failed to connect to remote server." );
    return;
  }
  debugPrint( "Sent the following to server: " );
  debugPrint( toSend );
  
  expectingResponse = true;
}

void pull()
{
  sendToServer("", false);
}

void recvTimer()
{
  if(expectingResponse)
  {
    handleResponse(getResponse()); 
  }
}

void handleResponse(String response)
{
  if(response.length() > 0)
  {
    debugPrint(" Server responded with: ");
    Serial.println(response);
    
    if(response.startsWith("[Suggestion]"))
    {
      Serial.print(" Suggestion intensity: ");
      String intensity = response.substring(13,14);
      
      notificationOn = true;
      sendToDribble(intensity);
    }
    else if(response.startsWith("[DEBUG]"))
    {
      Serial.print(" Debug: ");
      Serial.println(response.indexOf("[DEBUG]"));
      notificationOn = false;
    }
    else if(response.startsWith("[ERROR]"))
    {
      Serial.print(" Error: ");
      Serial.println(response.indexOf("[ERROR]"));
      notificationOn = false;
    }
    else if(response.startsWith("[ok]"))
    {
      Serial.print(" Ok: ");
      Serial.println(response.indexOf("[ok]"));
      notificationOn = false;
    }
    else if(response.startsWith("[IP]") && response.contains("[HOST]"))
    {
        String ipString = response.substring(4,response.indexOf("[HOST]"));
        String hostString = response.substring(response.indexOf("[HOST]"));
        Serial.println(ipString);
        Serial.println(hostString);
 /*       if(firstTagTrimmed
        
        p = strtok (str,".");
  
        while (p != NULL)
        {
          printf ("%s\n", p);
          p = strtok (NULL, ".");
        }*/
    }
    
    if(notificationOn)
      digitalWrite(9,HIGH);
    else
      digitalWrite(9,LOW);
    
  }
}

int MAX_RETRIES = 20;
int retry = 0;
String getResponse()
{
  String headers = String(RECV_BUFFER);
  String response = String(RECV_BUFFER);
  boolean foundHttpHeaders = false;
  while(client.available())
  {
    char c = client.read();
    if(headers.length() < RECV_BUFFER && foundHttpHeaders == false)
    {
      headers.append(c);
      if( headers.contains("\r\n\r\n") )
        foundHttpHeaders = true;
    }
    else
    {
      response.append(c);
    }
  }
  if(expectingResponse)
  {
    if(response.length() > 0)
    {
      expectingResponse = false;
      client.flush();
      client.stop();
    }
    else
    {
      /* don't close client because the response hasn't come yet...
         increase retry number until max retries... THEN close client
         if it isn't received by the final retry
       */
       if( retry < MAX_RETRIES )
       {
         retry++;
       }
       else
       {
         retry = 0;
         expectingResponse = false;
         client.flush();
         client.stop();
         debugPrint(" Expected a response but got none.");
       }
    }
    
  }
  
  return response;
}

/* Wrapper for Serial.println()... done like this to add debug tag
   and also in case later we'd like to see an output of debugging
   messages remotely.
*/
void debugPrint(char* toPrint)
{
  // commenting these lines out will disable debugging messages to the console.
  Serial.print( "DEBUG: " );
  Serial.println( toPrint );
}

void sendToDribble(String toSend)
{
  mySerial.print("(");
  mySerial.print(toSend);
  mySerial.println(")");
}

/* Diagnostic Event Functions:
   These functions send diagnostic events when called.
*/

/* This event is sent every once in a while telling the DB server that Dewey
   is still alive and well. A cron script on the server can check and see if
   there have been a few missed heartbeat events and if there are send an
   email to the researchers.
*/
void diagSendHeartbeatEvent()
{
  sendToServer("&com=diag&eventType=heartbeat", true);
}
/* This event is sent when the Arduino starts */
void diagSendStartEvent()
{
  sendToServer("&com=diag&eventType=turnedOn", true);
}
/* This event is sent when we receive something unexpected. */
void diagSendInvalidResponseEvent()
{
  sendToServer("&com=diag&eventType=invalidResponse", true);
}
