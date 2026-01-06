<template>
  <div class="quiz-container">
    <div class="quiz-header">
      <h2>{{ quiz.name }}</h2>
      <div class="timer" :class="{ 'warning': timeRemaining < 300 }">
        Time Remaining: {{ formatTime(timeRemaining) }}
      </div>
    </div>
    
    <div class="quiz-progress">
      <div class="progress-bar">
        <div class="progress" :style="{ width: progressPercentage + '%' }"></div>
      </div>
      <div class="progress-text">
        Question {{ currentQuestion + 1 }} of {{ questions.length }}
      </div>
    </div>
    
    <div class="question-container" v-if="currentQuestionData">
      <div class="question-text" v-html="currentQuestionData.text"></div>
      
      <div class="answers">
        <div v-for="(answer, index) in currentQuestionData.answers" 
             :key="index"
             class="answer-option"
             :class="{ 'selected': isSelected(index) }"
             @click="selectAnswer(index)">
          {{ answer.text }}
        </div>
      </div>
      
      <div class="navigation-buttons">
        <button @click="previousQuestion" :disabled="currentQuestion === 0">
          Previous
        </button>
        <button @click="nextQuestion" :disabled="currentQuestion === questions.length - 1">
          Next
        </button>
        <button @click="saveAnswer" class="btn-save">
          Save Answer
        </button>
      </div>
    </div>
    
    <div class="quiz-actions">
      <button @click="autoSave" class="btn-auto-save">
        Auto Save
      </button>
      <button @click="submitQuiz" class="btn-submit">
        Submit Quiz
      </button>
    </div>
    
    <div class="connection-status" :class="connectionClass">
      {{ connectionStatus }}
    </div>
  </div>
</template>

<script>
export default {
  props: {
    attemptId: {
      type: Number,
      required: true
    }
  },
  
  data() {
    return {
      quiz: {},
      questions: [],
      currentQuestion: 0,
      answers: {},
      timeRemaining: 0,
      timerInterval: null,
      autoSaveInterval: null,
      isOnline: true,
      lastSave: null
    };
  },
  
  computed: {
    currentQuestionData() {
      return this.questions[this.currentQuestion];
    },
    
    progressPercentage() {
      return ((this.currentQuestion + 1) / this.questions.length) * 100;
    },
    
    connectionStatus() {
      return this.isOnline ? 'Online' : 'Offline - Auto-saving locally';
    },
    
    connectionClass() {
      return this.isOnline ? 'online' : 'offline';
    }
  },
  
  mounted() {
    this.loadQuiz();
    this.startTimer();
    this.setupAutoSave();
    this.setupConnectionMonitoring();
  },
  
  beforeUnmount() {
    clearInterval(this.timerInterval);
    clearInterval(this.autoSaveInterval);
  },
  
  methods: {
    async loadQuiz() {
      try {
        const response = await axios.get(`/quiz/attempt/${this.attemptId}`);
        this.quiz = response.data.quiz;
        this.questions = response.data.questions;
        this.timeRemaining = response.data.time_remaining;
        this.answers = response.data.answers || {};
      } catch (error) {
        console.error('Failed to load quiz:', error);
      }
    },
    
    startTimer() {
      this.timerInterval = setInterval(() => {
        if (this.timeRemaining > 0) {
          this.timeRemaining--;
          
          // Auto-submit when time is up
          if (this.timeRemaining === 0) {
            this.submitQuiz();
          }
        }
      }, 1000);
    },
    
    formatTime(seconds) {
      const minutes = Math.floor(seconds / 60);
      const secs = seconds % 60;
      return `${minutes}:${secs.toString().padStart(2, '0')}`;
    },
    
    isSelected(index) {
      const questionId = this.currentQuestionData.id;
      return this.answers[questionId] && this.answers[questionId].answer === index;
    },
    
    selectAnswer(index) {
      const questionId = this.currentQuestionData.id;
      this.answers[questionId] = {
        answer: index,
        question_id: questionId,
        sequence_number: this.currentQuestion
      };
    },
    
    async saveAnswer() {
      const questionId = this.currentQuestionData.id;
      const answer = this.answers[questionId];
      
      if (!answer) return;
      
      try {
        await axios.post(`/quiz/attempt/${this.attemptId}/answer`, {
          question_id: questionId,
          answer: answer.answer,
          sequence_number: answer.sequence_number
        });
        
        this.lastSave = new Date();
      } catch (error) {
        console.error('Failed to save answer:', error);
      }
    },
    
    async autoSave() {
      try {
        await axios.post(`/quiz/attempt/${this.attemptId}/auto-save`, {
          answers: this.answers,
          current_question: this.currentQuestion,
          time_remaining: this.timeRemaining
        });
        
        this.lastSave = new Date();
      } catch (error) {
        console.error('Auto-save failed:', error);
        // Store locally if offline
        if (!this.isOnline) {
          localStorage.setItem(`quiz_${this.attemptId}`, JSON.stringify({
            answers: this.answers,
            timestamp: new Date().toISOString()
          }));
        }
      }
    },
    
    setupAutoSave() {
      this.autoSaveInterval = setInterval(() => {
        this.autoSave();
      }, 30000); // Auto-save every 30 seconds
    },
    
    setupConnectionMonitoring() {
      window.addEventListener('online', () => {
        this.isOnline = true;
        this.syncOfflineData();
      });
      
      window.addEventListener('offline', () => {
        this.isOnline = false;
      });
      
      // Check connection periodically
      setInterval(() => {
        this.isOnline = navigator.onLine;
      }, 5000);
    },
    
    async syncOfflineData() {
      const offlineData = localStorage.getItem(`quiz_${this.attemptId}`);
      if (offlineData) {
        try {
          const data = JSON.parse(offlineData);
          await this.autoSave();
          localStorage.removeItem(`quiz_${this.attemptId}`);
        } catch (error) {
          console.error('Failed to sync offline data:', error);
        }
      }
    },
    
    previousQuestion() {
      if (this.currentQuestion > 0) {
        this.currentQuestion--;
      }
    },
    
    nextQuestion() {
      if (this.currentQuestion < this.questions.length - 1) {
        this.currentQuestion++;
      }
    },
    
    async submitQuiz() {
      if (confirm('Are you sure you want to submit the quiz?')) {
        try {
          const response = await axios.post(`/quiz/attempt/${this.attemptId}/submit`);
          alert('Quiz submitted successfully!');
          window.location.href = '/quiz/results';
        } catch (error) {
          console.error('Failed to submit quiz:', error);
          alert('Failed to submit quiz. Please try again.');
        }
      }
    }
  }
};
</script>

<style scoped>
.quiz-container {
  max-width: 800px;
  margin: 0 auto;
  padding: 20px;
}

.quiz-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 20px;
}

.timer {
  font-size: 1.2em;
  font-weight: bold;
  padding: 10px 20px;
  border-radius: 5px;
  background: #4CAF50;
  color: white;
}

.timer.warning {
  background: #f44336;
  animation: pulse 1s infinite;
}

@keyframes pulse {
  0% { opacity: 1; }
  50% { opacity: 0.7; }
  100% { opacity: 1; }
}

.quiz-progress {
  margin-bottom: 30px;
}

.progress-bar {
  height: 10px;
  background: #e0e0e0;
  border-radius: 5px;
  overflow: hidden;
}

.progress {
  height: 100%;
  background: #4CAF50;
  transition: width 0.3s;
}

.question-container {
  background: white;
  padding: 30px;
  border-radius: 10px;
  box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.answers {
  margin: 20px 0;
}

.answer-option {
  padding: 15px;
  margin: 10px 0;
  border: 2px solid #ddd;
  border-radius: 5px;
  cursor: pointer;
  transition: all 0.3s;
}

.answer-option:hover {
  background: #f5f5f5;
}

.answer-option.selected {
  border-color: #4CAF50;
  background: #e8f5e9;
}

.navigation-buttons {
  display: flex;
  justify-content: space-between;
  margin-top: 30px;
}

button {
  padding: 10px 20px;
  border: none;
  border-radius: 5px;
  cursor: pointer;
  font-size: 16px;
}

.btn-save {
  background: #2196F3;
  color: white;
}

.btn-auto-save {
  background: #FF9800;
  color: white;
}

.btn-submit {
  background: #4CAF50;
  color: white;
}

.connection-status {
  position: fixed;
  bottom: 20px;
  right: 20px;
  padding: 10px 20px;
  border-radius: 5px;
  font-weight: bold;
}

.connection-status.online {
  background: #4CAF50;
  color: white;
}

.connection-status.offline {
  background: #f44336;
  color: white;
}
</style>